<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderTimeLog>
 */
class WorkOrderTimeLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-1 month', 'now');
        $durationMinutes = fake()->numberBetween(30, 480);
        $endTime = (clone $startTime)->modify("+{$durationMinutes} minutes");
        $hourlyRate = fake()->randomFloat(2, 25, 100);
        $totalCost = ($durationMinutes / 60) * $hourlyRate;

        return [
            'work_order_id' => WorkOrder::factory(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'hourly_rate' => $hourlyRate,
            'total_cost' => $totalCost,
            'description' => fake()->sentence(),
            'activity_type' => fake()->randomElement(['diagnosis', 'repair', 'testing', 'installation', 'documentation']),
        ];
    }
}

