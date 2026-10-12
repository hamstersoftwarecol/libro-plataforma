<?php

namespace App\Services;

use App\Enums\Role;
use App\Mail\LoginCodeMail;
use App\Mail\WelcomeMail;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginCodeService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_IN_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_CODES_PER_HOUR = 5;

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Generate a new one-time code and e-mail it (HU-002, HU-005).
     *
     * @throws ValidationException
     */
    public function send(string $email, ?string $ipAddress = null): void
    {
        $email = $this->normalize($email);

        $this->ensureNotThrottled('otp-cooldown:'.$email, 1, self::RESEND_COOLDOWN_SECONDS);
        $this->ensureNotThrottled('otp-hourly:'.$email, self::MAX_CODES_PER_HOUR, 3600);

        $code = $this->generateCode();

        DB::transaction(function () use ($email, $code, $ipAddress): void {
            LoginCode::query()->where('email', $email)->usable()->update(['consumed_at' => now()]);

            LoginCode::create([
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
                'ip_address' => $ipAddress,
            ]);
        });

        Mail::to($email)->send(new LoginCodeMail($code, self::EXPIRES_IN_MINUTES));

        $this->auditLogger->log('auth.code_sent', metadata: ['email' => $email]);
    }

    /**
     * Validate the code and return the (possibly new) user (HU-003, HU-004, HU-029).
     *
     * @throws ValidationException
     */
    public function verify(string $email, string $code): User
    {
        $email = $this->normalize($email);

        $loginCode = LoginCode::query()->where('email', $email)->usable()->latest('id')->first();

        if (! $loginCode || $loginCode->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => 'El código expiró o superó el número de intentos. Solicita uno nuevo.',
            ]);
        }

        if (! Hash::check($code, $loginCode->code_hash)) {
            $loginCode->increment('attempts');
            $this->auditLogger->log('auth.code_failed', metadata: ['email' => $email]);

            $remaining = self::MAX_ATTEMPTS - $loginCode->attempts;

            throw ValidationException::withMessages([
                'code' => $remaining > 0
                    ? "Código incorrecto. Te quedan {$remaining} intentos."
                    : 'Código incorrecto. Solicita un código nuevo.',
            ]);
        }

        $loginCode->update(['consumed_at' => now()]);

        $user = User::firstOrCreate(['email' => $email], ['role' => Role::Reader, 'is_active' => true]);

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'code' => 'Tu cuenta está desactivada. Contacta al soporte.',
            ]);
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'last_login_at' => now(),
        ])->save();

        if ($user->wasRecentlyCreated) {
            $this->auditLogger->log('user.created', $user, user: $user);
            Mail::to($user)->queue(new WelcomeMail($user));
        }

        return $user;
    }

    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** self::CODE_LENGTH - 1), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotThrottled(string $key, int $maxAttempts, int $decaySeconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Espera {$seconds} segundos antes de solicitar otro código.",
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
