<?php

namespace App\Services\Reader;

use App\Models\Book;
use App\Models\ReadingSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Limits how many devices can read at the same time (HU-029).
 */
class ReadingSessionManager
{
    /**
     * Open a new reading session and revoke the oldest ones over the limit.
     */
    public function start(User $user, Book $book, Request $request): ReadingSession
    {
        $session = ReadingSession::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'token' => Str::random(64),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            'last_seen_at' => now(),
        ]);

        $keep = ReadingSession::query()
            ->where('user_id', $user->id)
            ->live()
            ->latest('id')
            ->limit(max(1, config('reader.max_concurrent_sessions')))
            ->pluck('id');

        ReadingSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereNotIn('id', $keep)
            ->update(['revoked_at' => now()]);

        return $session;
    }

    /**
     * Validate the token and refresh its activity. Returns false when it was revoked or expired.
     */
    public function touch(User $user, Book $book, ?string $token): bool
    {
        $session = $this->find($user, $book, $token);

        if (! $session) {
            return false;
        }

        if ($session->last_seen_at->lt(now()->subMinute())) {
            $session->update(['last_seen_at' => now()]);
        }

        return true;
    }

    /**
     * The live session matching the token, if any.
     */
    public function find(User $user, Book $book, ?string $token): ?ReadingSession
    {
        if (! $token) {
            return null;
        }

        return ReadingSession::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->where('token', $token)
            ->live()
            ->first();
    }
}
