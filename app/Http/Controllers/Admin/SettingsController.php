<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SmtpTestMail;
use App\Models\Book;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Payments\BoldGateway;
use App\Services\Payments\Money;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Settings\RuntimeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private AuditLogger $auditLogger, private RuntimeConfig $runtimeConfig) {}

    /**
     * SMTP and payment gateway settings. Secret values are never sent back to the browser.
     */
    public function edit(): View
    {
        $smtp = config('mail.mailers.smtp');

        return view('admin.settings.edit', [
            'mail' => [
                'host' => Setting::get('mail.host', $smtp['host'] ?? ''),
                'port' => Setting::get('mail.port', (string) ($smtp['port'] ?? 465)),
                'encryption' => Setting::get('mail.encryption', ($smtp['scheme'] ?? null) === 'smtps' ? 'ssl' : 'tls'),
                'username' => Setting::get('mail.username', $smtp['username'] ?? ''),
                'from_address' => Setting::get('mail.from_address', config('mail.from.address')),
                'from_name' => Setting::get('mail.from_name', config('mail.from.name')),
                'has_password' => Setting::has('mail.password') || filled($smtp['password'] ?? null),
            ],
            'payments' => [
                'gateway' => config('payments.gateway'),
                'bold_environment' => config('payments.gateways.bold.environment', 'test'),
                'bold_identity_key' => config('payments.gateways.bold.identity_key'),
                'has_bold_secret' => filled(config('payments.gateways.bold.secret_key')),
                'webhook_url' => route('webhooks.payments', 'bold'),
                'return_url' => route('checkout.bold.return'),
                'incompatible_books' => Book::query()->published()->whereNotNull('price')->get()->reject(fn (Book $book): bool => $this->boldAccepts($book)),
                'sandbox_available' => (bool) config('payments.gateways.sandbox.enabled'),
            ],
        ]);
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:100'],
        ]);

        Setting::putMany(array_filter([
            'mail.host' => $validated['host'],
            'mail.port' => (string) $validated['port'],
            'mail.encryption' => $validated['encryption'],
            'mail.username' => $validated['username'] ?? '',
            'mail.password' => $validated['password'] ?? null,
            'mail.from_address' => $validated['from_address'],
            'mail.from_name' => $validated['from_name'],
        ], fn (?string $value) => $value !== null));

        $this->applied('settings.mail_updated', ['host' => $validated['host'], 'password_changed' => filled($validated['password'] ?? null)]);

        return back()->with('status', 'Configuración de correo guardada.');
    }

    /**
     * Send a test e-mail synchronously so errors are shown right away.
     */
    public function testMail(Request $request): RedirectResponse
    {
        try {
            Mail::mailer('smtp')->to($request->user())->send(new SmtpTestMail);
        } catch (Throwable $exception) {
            return back()->withErrors(['mail' => 'No se pudo enviar el correo de prueba: '.$exception->getMessage()]);
        }

        $this->auditLogger->log('settings.mail_tested');

        return back()->with('status', 'Correo de prueba enviado a '.$request->user()->email.'. Revisa tu bandeja de entrada y la de spam.');
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        $gateways = config('payments.gateways.sandbox.enabled') ? ['sandbox', 'bold'] : ['bold'];

        $validated = $request->validate([
            'gateway' => ['required', Rule::in($gateways)],
            'bold_environment' => ['required', Rule::in(['test', 'production'])],
            'bold_identity_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'bold_secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $identityKey = $validated['bold_identity_key'] ?? null;
        $hasSecret = filled($validated['bold_secret_key'] ?? null) || filled(config('payments.gateways.bold.secret_key'));

        if ($validated['gateway'] === 'bold' && (! $identityKey || ! $hasSecret)) {
            return back()->withInput()->withErrors(['bold_identity_key' => 'Para activar Bold ingresa la llave de identidad y la llave secreta.']);
        }

        Setting::putMany(array_filter([
            'payments.gateway' => $validated['gateway'],
            'bold.environment' => $validated['bold_environment'],
            'bold.identity_key' => $identityKey ?? '',
            'bold.secret_key' => $validated['bold_secret_key'] ?? null,
        ], fn (?string $value) => $value !== null));

        $this->applied('settings.payments_updated', ['gateway' => $validated['gateway'], 'environment' => $validated['bold_environment'], 'secret_changed' => filled($validated['bold_secret_key'] ?? null)]);

        return back()->with('status', 'Configuración de pagos guardada. Pasarela activa: '.($validated['gateway'] === 'bold' ? 'Bold' : 'pruebas internas').'.');
    }

    public function testBold(PaymentGatewayManager $gateways): RedirectResponse
    {
        if (! filled(config('payments.gateways.bold.identity_key'))) {
            return back()->withErrors(['bold' => 'Primero guarda la llave de identidad de Bold.']);
        }

        /** @var BoldGateway $gateway */
        $gateway = $gateways->driver('bold');
        $error = $gateway->testConnection();

        $this->auditLogger->log('settings.bold_tested', metadata: ['ok' => $error === null]);

        return $error
            ? back()->withErrors(['bold' => $error])
            : back()->with('status', 'Conexión con Bold correcta: la llave de identidad fue aceptada.');
    }

    private function boldAccepts(Book $book): bool
    {
        $major = Money::toMajor($book->price, $book->currency);

        return in_array($book->currency, BoldGateway::SUPPORTED_CURRENCIES, true)
            && floor($major) === $major
            && ($book->currency !== 'COP' || $major >= BoldGateway::MINIMUM_COP);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function applied(string $action, array $metadata): void
    {
        $this->runtimeConfig->apply();
        Artisan::call('queue:restart');
        $this->auditLogger->log($action, metadata: $metadata);
    }
}
