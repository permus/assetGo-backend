<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PredictiveMaintenance>
 */
class PredictiveMaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $estimatedCost = fake()->randomFloat(2, 500, 5000);
        $preventiveCost = fake()->randomFloat(2, 100, $estimatedCost * 0.5);
        $savings = $estimatedCost - $preventiveCost;

        return [
            'asset_id' => Asset::factory(),
            'risk_level' => fake()->randomElement(['low', 'medium', 'high']),
            'predicted_failure_date' => fake()->dateTimeBetween('now', '+1 year'),
            'confidence' => fake()->randomFloat(2, 60, 95),
            'recommended_action' => fake()->sentence(),
            'estimated_cost' => $estimatedCost,
            'preventive_cost' => $preventiveCost,
            'savings' => $savings,
            'factors' => [
                'age' => fake()->numberBetween(1, 10) . ' years',
                'usage_hours' => fake()->numberBetween(1000, 50000),
                'maintenance_history' => fake()->randomElement(['poor', 'average', 'good']),
            ],
            'timeline' => [
                '30_days' => fake()->randomFloat(2, 0, 30),
                '60_days' => fake()->randomFloat(2, 30, 60),
                '90_days' => fake()->randomFloat(2, 60, 90),
            ],
            'company_id' => Company::factory(),
        ];
    }
}

