<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderComment>
 */
class WorkOrderCommentFactory extends Factory
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
            'comment' => fake()->paragraph(),
            'meta' => fake()->optional()->passthrough(['type' => 'note', 'visibility' => 'internal']),
        ];
    }
}

