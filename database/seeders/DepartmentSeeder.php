<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Company;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate the table to avoid duplicates
        Department::truncate();
        
        // Get the first company (or create one if none exists)
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'name' => 'Default Company',
                'email' => 'admin@company.com',
                'phone' => '+1234567890',
                'address' => '123 Main St, City, State 12345',
            ]);
        }

        // Get the first user (or create one if none exists)
        $user = \App\Models\User::first();
        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'Admin User',
                'email' => 'admin@company.com',
                'password' => bcrypt('password'),
                'company_id' => $company->id,
            ]);
        }
        
        $departments = [
            [
                'name' => 'IT Department',
                'description' => 'Information Technology and Systems',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'IT',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Equipment and facility maintenance',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'MAINT',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Manufacturing',
                'description' => 'Production and manufacturing',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'MFG',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Operations',
                'description' => 'Daily operations and logistics',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'OPS',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Human Resources',
                'description' => 'HR and employee management',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'HR',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Finance',
                'description' => 'Financial management and accounting',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'FIN',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Sales',
                'description' => 'Sales and customer relations',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'SALES',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Marketing',
                'description' => 'Marketing and communications',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'MKT',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Research & Development',
                'description' => 'R&D and innovation',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'RND',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Quality Assurance',
                'description' => 'Quality control and testing',
                'company_id' => $company->id,
                'user_id' => $user->id,
                'code' => 'QA',
                'is_active' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
} 