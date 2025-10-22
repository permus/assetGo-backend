<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenancePlan>
 */
class MaintenancePlanFactory extends Factory
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
            'name' => fake()->words(3, true) . ' Maintenance Plan',
            'priority_id' => WorkOrderPriority::factory(),
            'sort' => fake()->numberBetween(1, 100),
            'descriptions' => fake()->paragraph(),
            'category_id' => WorkOrderCategory::factory(),
            'plan_type' => fake()->randomElement(['preventive', 'predictive', 'corrective']),
            'estimeted_duration' => fake()->numberBetween(30, 480),
            'instractions' => fake()->paragraph(),
            'safety_notes' => fake()->sentence(),
            'asset_ids' => [fake()->numberBetween(1, 100), fake()->numberBetween(1, 100)],
            'frequency_type' => fake()->randomElement(['calendar', 'meter', 'runtime']),
            'frequency_value' => fake()->numberBetween(1, 12),
            'frequency_unit' => fake()->randomElement(['days', 'weeks', 'months', 'years']),
            'is_active' => fake()->boolean(80),
        ];
    }
}

