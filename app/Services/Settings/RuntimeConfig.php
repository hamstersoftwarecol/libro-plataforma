<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Applies settings saved from the admin dashboard on top of the .env configuration.
 */
class RuntimeConfig
{
    public function apply(): void
    {
        try {
            $settings = Setting::allValues();
        } catch (QueryException) {
            return;
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $value = fn (string $key): ?string => isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : null;

        if ($value('mail.host')) {
            $encryption = $value('mail.encryption') ?? 'ssl';

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $value('mail.host'),
                'mail.mailers.smtp.port' => (int) ($value('mail.port') ?? 465),
                'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.require_tls' => $encryption === 'tls',
                'mail.mailers.smtp.username' => $value('mail.username'),
                'mail.mailers.smtp.password' => $value('mail.password'),
            ]);
        }

        if ($value('mail.from_address')) {
            config(['mail.from.address' => $value('mail.from_address')]);
        }

        if ($value('mail.from_name')) {
            config(['mail.from.name' => $value('mail.from_name')]);
        }

        if ($value('payments.gateway')) {
            config(['payments.gateway' => $value('payments.gateway')]);
        }

        config([
            'payments.gateways.bold.identity_key' => $value('bold.identity_key') ?? config('payments.gateways.bold.identity_key'),
            'payments.gateways.bold.secret_key' => $value('bold.secret_key') ?? config('payments.gateways.bold.secret_key'),
            'payments.gateways.bold.environment' => $value('bold.environment') ?? config('payments.gateways.bold.environment'),
        ]);
    }
}
