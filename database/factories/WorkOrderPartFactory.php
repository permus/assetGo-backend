<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\InventoryPart;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkOrderPart>
 */
class WorkOrderPartFactory extends Factory
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
            'part_id' => InventoryPart::factory(),
            'location_id' => Location::factory(),
            'qty' => fake()->randomFloat(3, 1, 100),
            'unit_cost' => fake()->randomFloat(2, 5, 500),
            'status' => fake()->randomElement(['requested', 'allocated', 'used', 'returned']),
            'meta' => fake()->optional()->passthrough(['notes' => fake()->sentence()]),
        ];
    }
}

