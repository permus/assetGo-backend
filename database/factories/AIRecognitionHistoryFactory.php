<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIRecognitionHistory>
 */
class AIRecognitionHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(1, 10),
            'company_id' => fake()->numberBetween(1, 5),
            'image_paths' => [
                'assets/images/' . fake()->uuid() . '.jpg',
                'assets/images/' . fake()->uuid() . '.jpg',
            ],
            'recognition_result' => [
                'confidence' => fake()->randomFloat(2, 70, 99),
                'detected_objects' => ['laptop', 'monitor', 'keyboard'],
                'suggestions' => ['Add to inventory', 'Tag as IT equipment'],
            ],
            'created_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'updated_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}

