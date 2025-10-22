<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderAssignment>
 */
class WorkOrderAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'user_id' => User::factory(),
            'assigned_by' => User::factory(),
            'status' => fake()->randomElement(['pending', 'accepted', 'declined', 'completed']),
        ];
    }
}

