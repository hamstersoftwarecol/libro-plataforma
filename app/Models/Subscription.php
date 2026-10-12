<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'plan_id', 'status', 'auto_renew', 'starts_at', 'ends_at', 'cancelled_at', 'expiry_reminder_sent_at'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
            'expiry_reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * A new end date needs a new expiry reminder.
     */
    protected static function booted(): void
    {
        static::updating(function (Subscription $subscription): void {
            if ($subscription->isDirty('ends_at') && ! $subscription->isDirty('expiry_reminder_sent_at')) {
                $subscription->expiry_reminder_sent_at = null;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function grantsAccess(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::Cancelled], true)
            && $this->starts_at->lte(now())
            && $this->ends_at->gt(now());
    }

    /**
     * Subscriptions granting access right now. Cancelled ones keep access until they end.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeGrantingAccess(Builder $query): void
    {
        $query->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Cancelled])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }
}
