<?php

namespace Database\Factories;

use App\Enums\BookFormat;
use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'title' => fake()->unique()->sentence(3),
            'subtitle' => fake()->optional()->sentence(4),
            'description' => fake()->paragraphs(2, true),
            'isbn' => fake()->isbn13(),
            'language' => 'es',
            'published_year' => fake()->numberBetween(1950, 2026),
            'page_count' => fake()->numberBetween(80, 600),
            'status' => BookStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookStatus::Published,
            'published_at' => now(),
            'file_path' => 'books/'.fake()->uuid().'.pdf',
            'file_format' => BookFormat::Pdf,
            'file_size' => 1024,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BookStatus::Inactive]);
    }
}
