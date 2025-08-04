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
                
                'assets' => [
                    'can_view' => $this->faker->boolean(80),
                    'can_create' => $this->faker->boolean(60),
                    'can_edit' => $this->faker->boolean(60),
                    'can_delete' => $this->faker->boolean(40),
                    'can_export' => $this->faker->boolean(70),
                ],
                'locations' => [
                    'can_view' => $this->faker->boolean(80),
                    'can_create' => $this->faker->boolean(60),
                    'can_edit' => $this->faker->boolean(60),
                    'can_delete' => $this->faker->boolean(40),
                    'can_export' => $this->faker->boolean(70),
                ],
                'work_orders' => [
                    'can_view' => $this->faker->boolean(70),
                    'can_create' => $this->faker->boolean(50),
                    'can_edit' => $this->faker->boolean(50),
                    'can_delete' => $this->faker->boolean(30),
                    'can_export' => $this->faker->boolean(60),
                ],
                'teams' => [
                    'can_view' => $this->faker->boolean(60),
                    'can_create' => $this->faker->boolean(40),
                    'can_edit' => $this->faker->boolean(40),
                    'can_delete' => $this->faker->boolean(20),
                    'can_export' => $this->faker->boolean(50),
                ],
                'maintenance' => [
                    'can_view' => $this->faker->boolean(70),
                    'can_create' => $this->faker->boolean(50),
                    'can_edit' => $this->faker->boolean(50),
                    'can_delete' => $this->faker->boolean(30),
                    'can_export' => $this->faker->boolean(60),
                ],
                'inventory' => [
                    'can_view' => $this->faker->boolean(70),
                    'can_create' => $this->faker->boolean(50),
                    'can_edit' => $this->faker->boolean(50),
                    'can_delete' => $this->faker->boolean(30),
                    'can_export' => $this->faker->boolean(60),
                ],
                'sensors' => [
                    'can_view' => $this->faker->boolean(60),
                    'can_create' => $this->faker->boolean(40),
                    'can_edit' => $this->faker->boolean(40),
                    'can_delete' => $this->faker->boolean(20),
                    'can_export' => $this->faker->boolean(50),
                ],
                'ai_features' => [
                    'can_view' => $this->faker->boolean(60),
                    'can_create' => $this->faker->boolean(40),
                    'can_edit' => $this->faker->boolean(40),
                    'can_delete' => $this->faker->boolean(20),
                    'can_export' => $this->faker->boolean(50),
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
     * Create permissions for an admin
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                    
                    'assets' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'locations' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'work_orders' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'teams' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'maintenance' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'inventory' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'sensors' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => true,
                        'can_export' => true,
                    ],
                    'ai_features' => [
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
     * Create permissions for a technician
     */
    public function technician(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                    
                    'assets' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'locations' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'work_orders' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'teams' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'maintenance' => [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'inventory' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'sensors' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'ai_features' => [
                        'can_view' => true,
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
     * Create permissions for a user
     */
    public function user(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'permissions' => [
                   
                    'assets' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'locations' => [
                        'can_view' => true,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'work_orders' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'teams' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'maintenance' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'inventory' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'sensors' => [
                        'can_view' => false,
                        'can_create' => false,
                        'can_edit' => false,
                        'can_delete' => false,
                        'can_export' => false,
                    ],
                    'ai_features' => [
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