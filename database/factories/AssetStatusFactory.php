<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetStatus>
 */
class AssetStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = [
            ['name' => 'Active', 'color' => 'green', 'description' => 'Asset is in active use'],
            ['name' => 'Inactive', 'color' => 'gray', 'description' => 'Asset is not currently in use'],
            ['name' => 'Maintenance', 'color' => 'yellow', 'description' => 'Asset is under maintenance'],
            ['name' => 'Retired', 'color' => 'red', 'description' => 'Asset has been retired'],
            ['name' => 'Pending', 'color' => 'blue', 'description' => 'Asset is pending deployment'],
            ['name' => 'Damaged', 'color' => 'orange', 'description' => 'Asset is damaged'],
            ['name' => 'Lost', 'color' => 'purple', 'description' => 'Asset is lost or missing'],
        ];

        $status = fake()->randomElement($statuses);
        
        return [
            'name' => $status['name'],
            'color' => $status['color'],
            'description' => $status['description'],
            'is_active' => fake()->boolean(80),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}

