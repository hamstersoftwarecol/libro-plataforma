<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Generate a unique slug from the source attribute when missing.
     */
    public static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            if (! $model->slug || $model->isDirty($model->slugSource())) {
                $model->slug = $model->uniqueSlug(Str::slug((string) $model->{$model->slugSource()}) ?: Str::random(8));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function slugSource(): string
    {
        return 'name';
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
