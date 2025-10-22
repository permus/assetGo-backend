<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetTag>
 */
class AssetTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tags = [
            'Critical', 'High Priority', 'Low Priority', 'Fragile', 'Heavy Equipment',
            'Portable', 'Fixed', 'Calibrated', 'Certified', 'Under Warranty',
            'Leased', 'Owned', 'Rental', 'Hazardous', 'Temperature Controlled',
            'Outdoor', 'Indoor', 'Mobile', 'Stationary', 'Networked',
        ];

        return [
            'name' => fake()->unique()->randomElement($tags),
        ];
    }
}

