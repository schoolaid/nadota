<?php

namespace Said\Nadota\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Said\Nadota\Tests\Models\Tag;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Said\Nadota\Tests\Models\Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}