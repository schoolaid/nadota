<?php

namespace SchoolAid\Nadota\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Said\Nadota\Tests\Models\TestModel>
 */
class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'description' => fake()->paragraph(),
            'age' => fake()->numberBetween(18, 80),
            'is_active' => fake()->boolean(),
            'published_at' => fake()->dateTime(),
            'metadata' => [
                'tags' => fake()->words(3),
                'category' => fake()->word(),
            ],
        ];
    }

    /**
     * Indicate that the model should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the model should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}