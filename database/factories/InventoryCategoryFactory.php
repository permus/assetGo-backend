<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryCategory>
 */
class InventoryCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            'Electrical Components', 'Mechanical Parts', 'Hydraulic Components', 
            'Pneumatic Parts', 'Lubricants & Oils', 'Fasteners', 'Bearings',
            'Filters', 'Belts & Chains', 'Seals & Gaskets', 'Safety Equipment',
            'Tools', 'Consumables', 'Cleaning Supplies'
        ];

        return [
            'company_id' => Company::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement($categories),
            'description' => fake()->sentence(),
            'is_active' => fake()->boolean(90),
        ];
    }
}

