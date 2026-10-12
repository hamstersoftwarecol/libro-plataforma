<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record a security or business relevant action (HU-028).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $action, ?Model $auditable = null, array $metadata = [], ?User $user = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'user_id' => ($user ?? Auth::user())?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }
}
