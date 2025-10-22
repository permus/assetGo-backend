<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetTransfer>
 */
class AssetTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'old_location_id' => Location::factory(),
            'new_location_id' => Location::factory(),
            'old_department_id' => Department::factory(),
            'new_department_id' => Department::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'transfer_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->paragraph(),
            'condition_report' => fake()->sentence(),
            'status' => fake()->randomElement(['pending', 'approved', 'completed', 'rejected']),
            'approved_by' => User::factory(),
            'created_by' => User::factory(),
        ];
    }
}

