<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocationType>
 */
class LocationTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Campus', 'Building', 'Floor', 'Room', 'Area']),
            'category' => fake()->randomElement(['Residential', 'Commercial', 'Industrial', 'Educational']),
            'hierarchy_level' => fake()->numberBetween(0, 3),
            'icon' => fake()->randomElement(['🏢', '🏠', '🏭', '🏫']),
            'suggestions' => json_encode(['suggestion1', 'suggestion2']),
        ];
    }
}
