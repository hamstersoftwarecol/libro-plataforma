<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Mail\LoginCodeMail;
use App\Models\AuditLog;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_login_page_is_displayed(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Correo electrónico');
    }

    public function test_requesting_a_code_sends_an_email_and_stores_a_hashed_code(): void
    {
        $this->post(route('login.send'), ['email' => '  Lector@Example.com '])
            ->assertRedirect(route('login.verify'))
            ->assertSessionHas('login.email', 'lector@example.com');

        $code = $this->sentCodeFor('lector@example.com');

        $loginCode = LoginCode::sole();
        $this->assertSame('lector@example.com', $loginCode->email);
        $this->assertNotSame($code, $loginCode->code_hash);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->post(route('login.send'), ['email' => 'no-es-un-correo'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_valid_code_creates_the_user_and_logs_in(): void
    {
        $this->post(route('login.send'), ['email' => 'nuevo@example.com']);
        $code = $this->sentCodeFor('nuevo@example.com');

        $this->post(route('login.verify.store'), ['code' => $code])->assertRedirect(route('book.start'));

        $user = User::where('email', 'nuevo@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Reader, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull(LoginCode::sole()->consumed_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('user_id', $user->id)->exists());
    }

    public function test_existing_admin_is_redirected_to_admin_dashboard(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->post(route('login.send'), ['email' => 'admin@example.com']);
        $code = $this->sentCodeFor('admin@example.com');

        $this->post(route('login.verify.store'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertDatabaseCount('users', 1);
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
        User::factory()->inactive()->create(['email' => 'bloqueado@example.com']);

        $this->post(route('login.send'), ['email' => 'bloqueado@example.com']);
        $code = $this->sentCodeFor('bloqueado@example.com');

        $this->post(route('login.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_verify_page_requires_an_email_in_session(): void
    {
        $this->get(route('login.verify'))->assertRedirect(route('login'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
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
