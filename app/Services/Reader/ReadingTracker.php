<?php

namespace App\Services\Reader;

use App\Models\Book;
use App\Models\ReadingDailyStat;
use App\Models\ReadingSession;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Aggregates reading activity per reader, book and day (HU-033).
 *
 * Time is the gap between consecutive signals of the same session, capped so
 * that an idle tab left open does not inflate the numbers.
 */
class ReadingTracker
{
    public const MAX_CREDITED_SECONDS = 180;

    public function record(ReadingSession $session, Book $book, bool $pageChanged): void
    {
        $now = now();
        $gap = $session->last_tracked_at ? (int) $session->last_tracked_at->diffInSeconds($now, true) : 0;
        $seconds = $gap <= self::MAX_CREDITED_SECONDS ? $gap : 0;
        $pages = $pageChanged ? 1 : 0;

        $session->forceFill(['last_tracked_at' => $now, 'last_seen_at' => $now])->saveQuietly();

        if ($seconds === 0 && $pages === 0) {
            return;
        }

        $this->increment($session->user_id, $book->id, $now->toDateString(), $pages, $seconds);
    }

    private function increment(int $userId, int $bookId, string $date, int $pages, int $seconds): void
    {
        $query = fn () => ReadingDailyStat::query()->where(['user_id' => $userId, 'book_id' => $bookId])->whereDate('date', $date);

        $updated = $query()->incrementEach(['pages_viewed' => $pages, 'seconds_read' => $seconds]);

        if ($updated > 0) {
            return;
        }

        try {
            ReadingDailyStat::create(['user_id' => $userId, 'book_id' => $bookId, 'date' => $date, 'pages_viewed' => $pages, 'seconds_read' => $seconds]);
        } catch (UniqueConstraintViolationException) {
            $query()->incrementEach(['pages_viewed' => $pages, 'seconds_read' => $seconds]);
        }
    }
}
