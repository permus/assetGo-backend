<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIAnalyticsRun>
 */
class AIAnalyticsRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'payload' => [
                'total_assets' => fake()->numberBetween(10, 1000),
                'analyzed_assets' => fake()->numberBetween(5, 1000),
                'issues_found' => fake()->numberBetween(0, 50),
                'recommendations' => fake()->numberBetween(0, 20),
            ],
            'health_score' => fake()->randomFloat(2, 60, 100),
        ];
    }
}

