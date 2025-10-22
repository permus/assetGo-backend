<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AITrainingData>
 */
class AITrainingDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => fake()->numberBetween(1, 100),
            'company_id' => fake()->numberBetween(1, 5),
            'image_path' => 'training/images/' . fake()->uuid() . '.jpg',
            'label' => fake()->randomElement(['laptop', 'monitor', 'keyboard', 'mouse', 'printer', 'scanner']),
            'metadata' => json_encode([
                'resolution' => '1920x1080',
                'file_size' => fake()->numberBetween(100000, 5000000),
            ]),
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}

