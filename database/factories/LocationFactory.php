<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use App\Models\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
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
            'location_type_id' => LocationType::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'address' => fake()->address(),
            'description' => fake()->sentence(),
            'qr_code_path' => null,
            'hierarchy_level' => 0,
        ];
    }
}
