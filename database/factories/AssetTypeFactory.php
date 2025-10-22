<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetType>
 */
class AssetTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ['name' => 'Equipment', 'icon' => 'wrench'],
            ['name' => 'Furniture', 'icon' => 'chair'],
            ['name' => 'Vehicle', 'icon' => 'car'],
            ['name' => 'Electronics', 'icon' => 'laptop'],
            ['name' => 'Machinery', 'icon' => 'cog'],
            ['name' => 'Tools', 'icon' => 'hammer'],
            ['name' => 'Computer Hardware', 'icon' => 'desktop'],
            ['name' => 'Mobile Devices', 'icon' => 'mobile'],
            ['name' => 'Office Equipment', 'icon' => 'printer'],
            ['name' => 'Building', 'icon' => 'building'],
        ];

        $type = fake()->randomElement($types);
        
        return [
            'name' => $type['name'],
            'icon' => $type['icon'],
        ];
    }
}

