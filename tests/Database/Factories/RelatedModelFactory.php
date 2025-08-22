<?php

namespace Said\Nadota\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Said\Nadota\Tests\Models\RelatedModel;
use Said\Nadota\Tests\Models\TestModel;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Said\Nadota\Tests\Models\RelatedModel>
 */
class RelatedModelFactory extends Factory
{
    protected $model = RelatedModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'test_model_id' => TestModel::factory(),
        ];
    }
}