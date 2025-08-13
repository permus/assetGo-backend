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
        // Optionally clear existing roles and permissions
        // Uncomment the next line if you want to start fresh
        // $this->clearExistingData();

        // Get all companies
        $companies = Company::all();

        foreach ($companies as $company) {
            // Create default roles for each company
            $this->createDefaultRoles($company);
        }
    }

    /**
     * Clear existing roles and permissions
     */
    private function clearExistingData(): void
    {
        // Delete permissions first due to foreign key constraints
        Permission::truncate();
        // Delete roles
        Role::truncate();
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
            // Check if role already exists for this company
            $existingRole = Role::where('name', $roleData['name'])
                ->where('company_id', $company->id)
                ->first();

            if ($existingRole) {
                // Update existing role description if needed
                $existingRole->update([
                    'description' => $roleData['description']
                ]);
                $role = $existingRole;
            } else {
                // Create new role
                $role = Role::create([
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'company_id' => $company->id,
                ]);
            }

            // Check if permission already exists for this role
            $existingPermission = Permission::where('role_id', $role->id)->first();
            
            if ($existingPermission) {
                // Update existing permissions
                $existingPermission->update([
                    'permissions' => $roleData['permissions']
                ]);
            } else {
                // Create new permissions for the role
                Permission::create([
                    'role_id' => $role->id,
                    'permissions' => $roleData['permissions'],
                ]);
            }
        }
    }
} 