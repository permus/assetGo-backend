<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InventoryPart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryAlert>
 */
class InventoryAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isResolved = fake()->boolean(30);
        
        return [
            'company_id' => Company::factory(),
            'part_id' => InventoryPart::factory(),
            'alert_type' => fake()->randomElement(['low_stock', 'out_of_stock', 'reorder_point', 'overstock', 'expiry']),
            'alert_level' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'message' => fake()->sentence(),
            'is_resolved' => $isResolved,
            'resolved_at' => $isResolved ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'resolved_by' => $isResolved ? User::factory() : null,
        ];
    }
}

