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
     * Create an administrator role
     */
    public function administrator(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Administrator',
                'description' => 'Full access to all features and settings',
            ];
        });
    }

    /**
     * Create a manager role
     */
    public function manager(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Manager',
                'description' => 'Can manage assets and locations, limited user management',
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
                'description' => 'Can view and edit assets, limited location access',
            ];
        });
    }

    /**
     * Create a viewer role
     */
    public function viewer(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Viewer',
                'description' => 'Read-only access to assets and locations',
            ];
        });
    }
} 