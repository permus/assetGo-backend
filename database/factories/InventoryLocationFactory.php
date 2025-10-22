<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = [
            'Main Warehouse', 'Secondary Warehouse', 'Parts Room', 'Tool Crib',
            'Maintenance Shop', 'Storage Area A', 'Storage Area B', 'Secure Storage',
            'Staging Area', 'Receiving Dock', 'Shipping Dock', 'Production Floor'
        ];

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement($locations) . ' ' . fake()->numberBetween(1, 10),
            'code' => strtoupper(fake()->bothify('LOC-???-##')),
            'description' => fake()->sentence(),
            'qr_code_path' => null,
        ];
    }
}

