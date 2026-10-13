<?php

namespace Tests\Feature\Admin;

use App\Mail\SmtpTestMail;
use App\Models\Book;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\RuntimeConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_can_open_settings(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Correo SMTP')->assertSee('Pagos con Bold');
        $this->actingAs(User::factory()->editor()->create())->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_smtp_settings_are_saved_encrypted_and_applied(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.mail'), [
            'host' => 'mail.ejemplo.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'libro@ejemplo.com',
            'password' => 'clave$secreta',
            'from_address' => 'libro@ejemplo.com',
            'from_name' => 'Mi Libro',
        ])->assertSessionHasNoErrors();

        $stored = DB::table('settings')->where('key', 'mail.password')->value('value');
        $this->assertNotSame('clave$secreta', $stored);
        $this->assertStringNotContainsString('clave', $stored);
        $this->assertSame('clave$secreta', Setting::get('mail.password'));

        app(RuntimeConfig::class)->apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('mail.ejemplo.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('clave$secreta', config('mail.mailers.smtp.password'));
        $this->assertSame('Mi Libro', config('mail.from.name'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.mail_updated']);
    }

    public function test_blank_password_keeps_the_saved_one_and_is_never_displayed(): void
    {
        Setting::putMany(['mail.password' => 'clave-original']);

        $this->actingAs($this->admin)->put(route('admin.settings.mail'), [
            'host' => 'mail.ejemplo.com', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'u', 'password' => '', 'from_address' => 'a@ejemplo.com', 'from_name' => 'Libro',
        ])->assertSessionHasNoErrors();

        $this->assertSame('clave-original', Setting::get('mail.password'));
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertDontSee('clave-original')->assertSee('guardada');
    }

    public function test_invalid_smtp_settings_are_rejected(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.mail'), [
            'host' => 'mal host;rm', 'port' => 99999, 'encryption' => 'otro', 'from_address' => 'no-es-correo', 'from_name' => '',
        ])->assertSessionHasErrors(['host', 'port', 'encryption', 'from_address', 'from_name']);
    }

    public function test_test_email_is_sent_to_the_admin(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->post(route('admin.settings.mail.test'))->assertSessionHas('status');

        Mail::assertSent(SmtpTestMail::class, fn (SmtpTestMail $mail) => $mail->hasTo($this->admin->email));
    }

    public function test_bold_requires_keys_before_activation(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.payments'), [
            'gateway' => 'bold', 'bold_environment' => 'test',
        ])->assertSessionHasErrors('bold_identity_key');

        $this->assertNull(Setting::get('payments.gateway'));
    }

    public function test_bold_settings_are_saved_and_activate_the_gateway(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.payments'), [
            'gateway' => 'bold', 'bold_environment' => 'production',
            'bold_identity_key' => 'IDENTIDAD_123', 'bold_secret_key' => 'SECRETA_456',
        ])->assertSessionHasNoErrors();

        $this->assertNotSame('SECRETA_456', DB::table('settings')->where('key', 'bold.secret_key')->value('value'));

        app(RuntimeConfig::class)->apply();
        $this->assertSame('bold', config('payments.gateway'));
        $this->assertSame('IDENTIDAD_123', config('payments.gateways.bold.identity_key'));
        $this->assertSame('SECRETA_456', config('payments.gateways.bold.secret_key'));
        $this->assertSame('production', config('payments.gateways.bold.environment'));

        $this->actingAs($this->admin)->get(route('admin.settings.edit'))
            ->assertSee('IDENTIDAD_123')
            ->assertDontSee('SECRETA_456')
            ->assertSee(route('webhooks.payments', 'bold'));
    }

    public function test_bold_connection_check(): void
    {
        Setting::putMany(['bold.identity_key' => 'IDENTIDAD_123']);
        app(RuntimeConfig::class)->apply();

        Http::fake(['payments.api.bold.co/*' => Http::sequence()
            ->push(['message' => 'not found'], 404)
            ->push(['message' => 'unauthorized'], 401)]);

        $this->actingAs($this->admin)->post(route('admin.settings.payments.test'))->assertSessionHas('status');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'x-api-key IDENTIDAD_123'));

        $this->actingAs($this->admin)->post(route('admin.settings.payments.test'))->assertSessionHasErrors('bold');
    }

    public function test_settings_page_warns_about_book_prices_bold_cannot_charge(): void
    {
        Book::factory()->published()->create(['title' => 'Libro en dólares', 'price' => 999, 'currency' => 'USD']);
        Book::factory()->published()->create(['title' => 'Libro en pesos', 'price' => 29900, 'currency' => 'COP']);

        $this->actingAs($this->admin)->get(route('admin.settings.edit'))
            ->assertSee('Libros con precio no compatible con Bold')
            ->assertSee('Libro en dólares')
            ->assertDontSee('Libro en pesos (');
    }
}
