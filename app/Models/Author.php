<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'bio'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, HasSlug;

    /**
     * @return BelongsToMany<Book, $this>
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
