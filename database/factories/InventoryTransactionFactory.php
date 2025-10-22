<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\InventoryPart;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['in', 'out', 'adjustment', 'transfer', 'return', 'scrap'];
        $type = fake()->randomElement($types);
        $quantity = fake()->numberBetween(1, 100);
        $unitCost = fake()->randomFloat(2, 5, 500);
        
        return [
            'company_id' => Company::factory(),
            'part_id' => InventoryPart::factory(),
            'location_id' => Location::factory(),
            'from_location_id' => $type === 'transfer' ? Location::factory() : null,
            'to_location_id' => $type === 'transfer' ? Location::factory() : null,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'reason' => fake()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'reference' => fake()->optional()->bothify('REF-####-???'),
            'reference_type' => fake()->optional()->randomElement(['work_order', 'purchase_order', 'asset']),
            'reference_id' => fake()->optional()->numberBetween(1, 1000),
            'related_id' => null,
            'user_id' => User::factory(),
        ];
    }
}

