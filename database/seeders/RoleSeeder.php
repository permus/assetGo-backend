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
                'name' => 'Administrator',
                'description' => 'Full access to all features and settings',
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
                ]
            ],
            [
                'name' => 'Manager',
                'description' => 'Can manage assets and locations, limited user management',
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
                ]
            ],
            [
                'name' => 'Technician',
                'description' => 'Can view and edit assets, limited location access',
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
                ]
            ],
            [
                'name' => 'Viewer',
                'description' => 'Read-only access to assets and locations',
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