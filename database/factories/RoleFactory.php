<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
            'description' => $this->faker->sentence(),
            'company_id' => Company::factory(),
        ];
    }

    /**
     * Create a role with specific permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->state(function (array $attributes) use ($permissions) {
            return [
                'permissions' => $permissions,
            ];
        });
    }

    /**
     * Create an admin role
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Admin',
                'description' => 'Full access to all features and settings',
            ];
        });
    }

    /**
     * Create a technician role
     */
    public function technician(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Technician',
                'description' => 'Can view and edit assets, locations, and work orders',
            ];
        });
    }

    /**
     * Create a user role
     */
    public function user(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'User',
                'description' => 'Basic access to view assets and locations',
            ];
        });
    }
} 