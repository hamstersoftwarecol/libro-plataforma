<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'is_active', 'email_verified_at', 'last_login_at'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'role' => Role::class,
        ];
    }

    /**
     * Passwordless accounts have no password to verify.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<ReadingProgress, $this>
     */
    public function readingProgress(): HasMany
    {
        return $this->hasMany(ReadingProgress::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The subscription currently granting access, if any.
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->grantingAccess()->with('plan')->latest('ends_at')->first();
    }

    /**
     * Whether the user currently has a subscription granting access (HU-013, HU-019).
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()->grantingAccess()->exists();
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Display name, falling back to the e-mail local part.
     */
    public function displayName(): string
    {
        return $this->name ?: strstr($this->email, '@', true);
    }
}
