<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'book_id', 'token', 'ip_address', 'user_agent', 'last_seen_at', 'last_tracked_at', 'revoked_at'])]
#[Hidden(['token'])]
class ReadingSession extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_tracked_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sessions not revoked and seen within the idle timeout.
     *
     * @param  Builder<ReadingSession>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where('last_seen_at', '>', now()->subMinutes(config('reader.session_idle_minutes')));
    }
}
