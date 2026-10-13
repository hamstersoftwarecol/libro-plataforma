<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginCodeMail;
use App\Models\AuditLog;
use App\Models\LoginCode;
use App\Models\Subscription;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->customer = User::factory()->create(['email' => 'lector@example.com']);
        Subscription::factory()->for($this->customer)->create();
    }

    public function test_login_page_is_displayed_with_a_link_to_buy(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Correo electrónico')->assertSee('Cómpralo aquí');
    }

    public function test_paying_customer_receives_a_hashed_code(): void
    {
        $this->post(route('login.send'), ['email' => '  Lector@Example.com '])
            ->assertRedirect(route('login.verify'))
            ->assertSessionHas('login.email', 'lector@example.com');

        $code = $this->sentCodeFor('lector@example.com');

        $loginCode = LoginCode::sole();
        $this->assertSame('lector@example.com', $loginCode->email);
        $this->assertNotSame($code, $loginCode->code_hash);
    }

    public function test_unknown_emails_cannot_sign_in_and_are_invited_to_buy(): void
    {
        $this->post(route('login.send'), ['email' => 'nuevo@example.com'])
            ->assertSessionHasErrors(['email' => 'Para ingresar necesitas haber comprado el libro con este correo.'])
            ->assertSessionHas('needs_purchase', true);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
        $this->from(route('login'))->followingRedirects()->post(route('login.send'), ['email' => 'nuevo@example.com'])->assertSee('Cómpralo y empieza a leer');
    }

    public function test_customers_without_an_active_subscription_cannot_sign_in(): void
    {
        $expired = User::factory()->create(['email' => 'vencido@example.com']);
        Subscription::factory()->for($expired)->expired()->create();

        $this->post(route('login.send'), ['email' => 'vencido@example.com'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_staff_can_sign_in_without_a_subscription(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->post(route('login.send'), ['email' => 'admin@example.com']);
        $this->post(route('login.verify.store'), ['code' => $this->sentCodeFor('admin@example.com')])->assertRedirect(route('admin.dashboard'));
    }

    public function test_mail_server_failure_shows_a_friendly_error_instead_of_crashing(): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException('Connection could not be established'));

        $this->post(route('login.send'), ['email' => 'lector@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => 'No pudimos enviar el correo en este momento. Inténtalo de nuevo en unos minutos.']);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->post(route('login.send'), ['email' => 'no-es-un-correo'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_valid_code_logs_the_customer_in(): void
    {
        $this->post(route('login.send'), ['email' => 'lector@example.com']);

        $this->post(route('login.verify.store'), ['code' => $this->sentCodeFor('lector@example.com')])->assertRedirect(route('book.start'));

        $this->assertAuthenticatedAs($this->customer);
        $this->assertNotNull($this->customer->fresh()->last_login_at);
        $this->assertNotNull(LoginCode::sole()->consumed_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('user_id', $this->customer->id)->exists());
        $this->assertSame(1, User::count());
    }

    public function test_code_cannot_be_reused(): void
    {
        $this->post(route('login.send'), ['email' => 'lector@example.com']);
        $code = $this->sentCodeFor('lector@example.com');
        $this->post(route('login.verify.store'), ['code' => $code]);
        $this->post(route('logout'));

        $this->withSession(['login.email' => 'lector@example.com'])
            ->post(route('login.verify.store'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_code_expires_after_five_minutes(): void
    {
        $this->assertSame(5, LoginCodeService::EXPIRES_IN_MINUTES);

        $this->post(route('login.send'), ['email' => 'lector@example.com']);
        $code = $this->sentCodeFor('lector@example.com');

        $this->travel(4)->minutes();
        $this->travel(59)->seconds();
        $this->post(route('login.verify.store'), ['code' => $code])->assertRedirect(route('book.start'));
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->post(route('login.send'), ['email' => 'lector@example.com']);
        $code = $this->sentCodeFor('lector@example.com');

        $this->travel(LoginCodeService::EXPIRES_IN_MINUTES + 1)->minutes();

        $this->post(route('login.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_code_is_invalidated_after_max_failed_attempts(): void
    {
        $this->post(route('login.send'), ['email' => 'lector@example.com']);
        $code = $this->sentCodeFor('lector@example.com');
        $wrongCode = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < LoginCodeService::MAX_ATTEMPTS; $i++) {
            $this->post(route('login.verify.store'), ['code' => $wrongCode])->assertSessionHasErrors('code');
        }

        $this->post(route('login.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_resend_is_rate_limited(): void
    {
        $this->post(route('login.send'), ['email' => 'lector@example.com']);

        $this->post(route('login.resend'))->assertSessionHasErrors('email');
        Mail::assertSentCount(1);

        $this->travel(LoginCodeService::RESEND_COOLDOWN_SECONDS + 1)->seconds();

        $this->post(route('login.resend'))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);
        $this->assertSame(1, LoginCode::usable()->count());
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $blocked = User::factory()->inactive()->create(['email' => 'bloqueado@example.com']);
        Subscription::factory()->for($blocked)->create();

        $this->post(route('login.send'), ['email' => 'bloqueado@example.com'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertGuest();
    }

    public function test_verify_page_requires_an_email_in_session(): void
    {
        $this->get(route('login.verify'))->assertRedirect(route('login'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->customer)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    private function sentCodeFor(string $email): string
    {
        $code = null;

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($email, &$code): bool {
            $code = $mail->code;

            return $mail->hasTo($email);
        });

        return $code;
    }
}
