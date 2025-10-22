<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenancePlanChecklist>
 */
class MaintenancePlanChecklistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'title' => fake()->sentence(),
            'type' => fake()->randomElement(['checkbox', 'text', 'number', 'photo']),
            'description' => fake()->sentence(),
            'is_required' => fake()->boolean(70),
            'is_safety_critical' => fake()->boolean(30),
            'is_photo_required' => fake()->boolean(20),
            'order' => fake()->numberBetween(1, 100),
        ];
    }
}

