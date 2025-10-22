<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AIRecommendation>
 */
class AIRecommendationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $estimatedSavings = fake()->randomFloat(2, 1000, 50000);
        $implementationCost = fake()->randomFloat(2, 500, $estimatedSavings * 0.5);
        $roi = (($estimatedSavings - $implementationCost) / $implementationCost) * 100;

        return [
            'company_id' => Company::factory(),
            'rec_type' => fake()->randomElement(['cost_optimization', 'maintenance', 'efficiency', 'compliance']),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'impact' => fake()->randomElement(['low', 'medium', 'high']),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'estimated_savings' => $estimatedSavings,
            'implementation_cost' => $implementationCost,
            'roi' => $roi,
            'payback_period' => fake()->numberBetween(1, 24) . ' months',
            'timeline' => fake()->randomElement(['immediate', '1-3 months', '3-6 months', '6-12 months']),
            'actions' => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
            'confidence' => fake()->randomFloat(2, 60, 95),
            'implemented' => fake()->boolean(30),
        ];
    }
}

