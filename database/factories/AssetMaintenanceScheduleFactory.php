<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetMaintenanceSchedule>
 */
class AssetMaintenanceScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'schedule_type' => fake()->randomElement(['Preventive', 'Predictive', 'Routine', 'Inspection']),
            'next_due' => fake()->dateTimeBetween('now', '+6 months'),
            'last_done' => fake()->dateTimeBetween('-6 months', 'now'),
            'frequency' => fake()->randomElement(['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly']),
            'notes' => fake()->paragraph(),
            'status' => fake()->randomElement(['active', 'inactive', 'completed']),
        ];
    }
}

