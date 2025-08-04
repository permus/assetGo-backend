<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Company;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all companies
        $companies = Company::all();

        foreach ($companies as $company) {
            // Create default roles for each company
            $this->createDefaultRoles($company);
        }
    }

    /**
     * Create default roles for a company
     */
    private function createDefaultRoles(Company $company): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Full access to all features and settings',
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
                    
                ]
            ],
            [
                'name' => 'Technician',
                'description' => 'Can view and edit assets, locations, and work orders',
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
                   
                ]
            ],
            [
                'name' => 'User',
                'description' => 'Basic access to view assets and locations',
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
                
                ]
            ],
        ];

        foreach ($roles as $roleData) {
            // Create role
            $role = Role::create([
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'company_id' => $company->id,
            ]);

            // Create permissions for the role
            Permission::create([
                'role_id' => $role->id,
                'permissions' => $roleData['permissions'],
            ]);
        }
    }
} 