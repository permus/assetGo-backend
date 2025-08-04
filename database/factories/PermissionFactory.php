<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'permissions' => [
                'location' => [
                    'can_view' => $this->faker->boolean(80),
                    'can_create' => $this->faker->boolean(60),
                    'can_edit' => $this->faker->boolean(60),
                    'can_delete' => $this->faker->boolean(40),
                    'can_export' => $this->faker->boolean(70),
                ],
                'assets' => [
                    'can_view' => $this->faker->boolean(80),
                    'can_create' => $this->faker->boolean(60),
                    'can_edit' => $this->faker->boolean(60),
                    'can_delete' => $this->faker->boolean(40),
                    'can_export' => $this->faker->boolean(70),
                ],
                'users' => [
                    'can_view' => $this->faker->boolean(60),
                    'can_create' => $this->faker->boolean(40),
                    'can_edit' => $this->faker->boolean(40),
                    'can_delete' => $this->faker->boolean(20),
                    'can_export' => $this->faker->boolean(50),
                ],
                'roles' => [
                    'can_view' => $this->faker->boolean(50),
                    'can_create' => $this->faker->boolean(30),
                    'can_edit' => $this->faker->boolean(30),
                    'can_delete' => $this->faker->boolean(20),
                    'can_export' => $this->faker->boolean(40),
                ],
                'reports' => [
                    'can_view' => $this->faker->boolean(70),
                    'can_create' => $this->faker->boolean(50),
                    'can_edit' => $this->faker->boolean(50),
                    'can_delete' => $this->faker->boolean(30),
                    'can_export' => $this->faker->boolean(80),
                ],
            ],
        ];
    }

    /**
     * Create permissions for an administrator
     */
    public function administrator(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                    'location' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'assets' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'users' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'roles' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'reports' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                ],
            ];
        });
    }

    /**
     * Create permissions for a manager
     */
    public function manager(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                    'location' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => true,
                    ],
                    'assets' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => true,
                    ],
                    'users' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'roles' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'reports' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => true,
                    ],
                ],
            ];
        });
    }

    /**
     * Create permissions for a technician
     */
    public function technician(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                    'location' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'assets' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'users' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'roles' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'reports' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                ],
            ];
        });
    }

    /**
     * Create permissions for a viewer
     */
    public function viewer(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                    'location' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'assets' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'users' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'roles' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'reports' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                ],
            ];
        });
    }
} 