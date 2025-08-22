<?php

namespace SchoolAid\Nadota\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SchoolAid\Nadota\Tests\Models\Profile;
use SchoolAid\Nadota\Tests\Models\TestModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Said\Nadota\Tests\Models\Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bio' => fake()->paragraph(),
            'test_model_id' => TestModel::factory(),
        ];
    }
}