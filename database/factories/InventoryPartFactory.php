<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\Supplier;
use App\Models\InventoryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryPart>
 */
class InventoryPartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'part_number' => strtoupper(fake()->bothify('PN-####-???')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'manufacturer' => fake()->company(),
            'maintenance_category' => fake()->randomElement(['Electrical', 'Mechanical', 'Hydraulic', 'Pneumatic', 'Electronics']),
            'uom' => fake()->randomElement(['PCS', 'KG', 'M', 'L', 'BOX', 'SET']),
            'unit_cost' => fake()->randomFloat(2, 5, 500),
            'specifications' => ['weight' => fake()->randomFloat(2, 0.1, 10) . ' kg', 'dimensions' => fake()->numerify('## x ## x ## cm')],
            'compatible_assets' => ['AST-' . fake()->numerify('####'), 'AST-' . fake()->numerify('####')],
            'category_id' => InventoryCategory::factory(),
            'reorder_point' => fake()->numberBetween(5, 20),
            'reorder_qty' => fake()->numberBetween(20, 100),
            'minimum_stock' => fake()->numberBetween(5, 15),
            'maximum_stock' => fake()->numberBetween(100, 500),
            'is_consumable' => fake()->boolean(),
            'usage_tracking' => fake()->boolean(70),
            'preferred_supplier_id' => null,
            'barcode' => fake()->ean13(),
            'image_path' => null,
            'status' => fake()->randomElement(['active', 'inactive', 'discontinued']),
            'abc_class' => fake()->randomElement(['A', 'B', 'C']),
            'extra' => null,
        ];
    }
}

