<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Location;
use App\Models\Department;
use App\Models\User;
use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => 'AST-' . fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category_id' => AssetCategory::factory(),
            'type' => fake()->randomElement(['Equipment', 'Furniture', 'Vehicle', 'Electronics']),
            'serial_number' => fake()->unique()->regexify('[A-Z0-9]{10}'),
            'model' => fake()->word(),
            'manufacturer' => fake()->company(),
            'purchase_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'purchase_price' => fake()->randomFloat(2, 100, 10000),
            'depreciation' => fake()->randomFloat(2, 0, 1000),
            'location_id' => Location::factory(),
            'department_id' => Department::factory(),
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'warranty' => fake()->dateTimeBetween('now', '+2 years'),
            'insurance' => fake()->word(),
            'health_score' => fake()->numberBetween(0, 100),
            'status' => fake()->randomElement(['active', 'inactive', 'maintenance']),
            'qr_code_path' => null,
            'parent_id' => null,
        ];
    }
}
