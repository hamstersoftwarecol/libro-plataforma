<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage-books');
    }

    /**
     * Readers only see published books; staff see everything.
     */
    public function view(User $user, Book $book): bool
    {
        return $book->isPublished() || $user->can('manage-books');
    }

    /**
     * Reading requires an active account, a readable file and, for readers, an active subscription (HU-013).
     */
    public function read(User $user, Book $book): bool
    {
        if (! $user->is_active || ! $book->hasFile() || $book->unitCount() < 1) {
            return false;
        }

        if ($user->can('manage-books')) {
            return true;
        }

        return $book->isPublished() && $user->hasActiveSubscription();
    }

    public function create(User $user): bool
    {
        return $user->can('manage-books');
    }

    public function update(User $user, Book $book): bool
    {
        return $user->can('manage-books');
    }

    public function publish(User $user, Book $book): bool
    {
        return $user->can('manage-books');
    }

    /**
     * Only administrators can permanently delete a book.
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->isAdmin();
    }
}
