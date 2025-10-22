<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\InventoryPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderedQty = fake()->numberBetween(1, 100);
        $receivedQty = fake()->numberBetween(0, $orderedQty);
        $unitCost = fake()->randomFloat(2, 5, 500);
        $lineTotal = $orderedQty * $unitCost;

        return [
            'company_id' => Company::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'part_id' => InventoryPart::factory(),
            'part_number' => strtoupper(fake()->bothify('PN-####-???')),
            'description' => fake()->sentence(),
            'ordered_qty' => $orderedQty,
            'received_qty' => $receivedQty,
            'unit_cost' => $unitCost,
            'line_total' => $lineTotal,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

