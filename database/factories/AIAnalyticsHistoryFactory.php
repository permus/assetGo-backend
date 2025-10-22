<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIAnalyticsHistory>
 */
class AIAnalyticsHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'asset_count' => fake()->numberBetween(10, 1000),
            'image_count' => fake()->numberBetween(5, 500),
            'analytics_result' => [
                'total_value' => fake()->randomFloat(2, 10000, 1000000),
                'health_distribution' => [
                    'excellent' => fake()->numberBetween(0, 100),
                    'good' => fake()->numberBetween(0, 100),
                    'fair' => fake()->numberBetween(0, 100),
                    'poor' => fake()->numberBetween(0, 100),
                ],
                'recommendations' => fake()->numberBetween(0, 20),
            ],
            'health_score' => fake()->randomFloat(2, 60, 100),
        ];
    }
}

