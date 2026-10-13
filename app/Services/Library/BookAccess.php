<?php

namespace App\Services\Library;

use App\Models\Book;
use App\Models\Payment;
use App\Models\ReadingSession;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Lifetime ownership of individual books.
 */
class BookAccess
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Grant lifetime access. Granting twice is harmless.
     */
    public function grant(User $user, Book $book, ?Payment $payment = null, ?User $grantedBy = null): void
    {
        if ($user->ownsBook($book)) {
            return;
        }

        $user->books()->attach($book->id, [
            'payment_id' => $payment?->id,
            'granted_by' => $grantedBy?->id,
            'granted_at' => now(),
        ]);

        $this->auditLogger->log($payment ? 'book.purchased' : 'book.access_granted', $book, array_filter([
            'user' => $user->email,
            'payment' => $payment?->reference,
        ]), $grantedBy ?? $user);
    }

    public function revoke(User $user, Book $book, ?User $revokedBy = null): void
    {
        $user->books()->detach($book->id);
        ReadingSession::where('user_id', $user->id)->where('book_id', $book->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $this->auditLogger->log('book.access_revoked', $book, ['user' => $user->email], $revokedBy);
    }
}
