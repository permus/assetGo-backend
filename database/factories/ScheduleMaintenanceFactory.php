<?php

namespace Database\Factories;

use App\Models\MaintenancePlan;
use App\Models\WorkOrderPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleMaintenance>
 */
class ScheduleMaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $dueDate = fake()->dateTimeBetween($startDate, '+3 months');

        return [
            'maintenance_plan_id' => MaintenancePlan::factory(),
            'asset_ids' => [fake()->numberBetween(1, 100), fake()->numberBetween(1, 100)],
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'status' => fake()->randomElement(['scheduled', 'in_progress', 'completed', 'cancelled', 'overdue']),
            'priority_id' => WorkOrderPriority::factory(),
        ];
    }
}

