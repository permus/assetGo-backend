<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssetActivity>
 */
class AssetActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = [
            'created', 'updated', 'assigned', 'transferred', 'maintenance_scheduled',
            'maintenance_completed', 'status_changed', 'retired', 'restored'
        ];

        return [
            'asset_id' => Asset::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement($actions),
            'before' => ['status' => 'active', 'location' => 'Office A'],
            'after' => ['status' => 'maintenance', 'location' => 'Workshop'],
            'comment' => fake()->sentence(),
        ];
    }
}

