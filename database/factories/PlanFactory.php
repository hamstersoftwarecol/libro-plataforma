<?php

namespace Database\Factories;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Plan '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'interval' => PlanInterval::Monthly,
            'price' => 999,
            'currency' => 'USD',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => ['interval' => PlanInterval::Yearly, 'price' => 9900]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
