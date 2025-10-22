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
        if (Department::count() >= 20) {
            $this->command->info('Departments already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding departments...');

        $departments = [
            [
                'name' => 'IT Department',
                'description' => 'Information Technology and Systems',
                'code' => 'IT',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Equipment and facility maintenance',
                'code' => 'MAINT',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Manufacturing',
                'description' => 'Production and manufacturing',
                'code' => 'MFG',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Operations',
                'description' => 'Daily operations and logistics',
                'code' => 'OPS',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Human Resources',
                'description' => 'HR and employee management',
                'code' => 'HR',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Finance',
                'description' => 'Financial management and accounting',
                'code' => 'FIN',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Sales',
                'description' => 'Sales and customer relations',
                'code' => 'SALES',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Marketing',
                'description' => 'Marketing and communications',
                'code' => 'MKT',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Research & Development',
                'description' => 'R&D and innovation',
                'code' => 'RND',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Quality Assurance',
                'description' => 'Quality control and testing',
                'code' => 'QA',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Customer Support',
                'description' => 'Customer service and support',
                'code' => 'CS',
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'name' => 'Legal',
                'description' => 'Legal affairs and compliance',
                'code' => 'LEGAL',
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'name' => 'Procurement',
                'description' => 'Purchasing and vendor management',
                'code' => 'PROC',
                'is_active' => true,
                'sort_order' => 13,
            ],
            [
                'name' => 'Logistics',
                'description' => 'Supply chain and logistics',
                'code' => 'LOG',
                'is_active' => true,
                'sort_order' => 14,
            ],
            [
                'name' => 'Warehouse',
                'description' => 'Warehouse operations',
                'code' => 'WH',
                'is_active' => true,
                'sort_order' => 15,
            ],
            [
                'name' => 'Security',
                'description' => 'Physical and IT security',
                'code' => 'SEC',
                'is_active' => true,
                'sort_order' => 16,
            ],
            [
                'name' => 'Facilities',
                'description' => 'Facilities management',
                'code' => 'FAC',
                'is_active' => true,
                'sort_order' => 17,
            ],
            [
                'name' => 'Training',
                'description' => 'Employee training and development',
                'code' => 'TRN',
                'is_active' => true,
                'sort_order' => 18,
            ],
            [
                'name' => 'Environmental',
                'description' => 'Environmental health and safety',
                'code' => 'EHS',
                'is_active' => true,
                'sort_order' => 19,
            ],
            [
                'name' => 'Business Development',
                'description' => 'Business development and partnerships',
                'code' => 'BD',
                'is_active' => true,
                'sort_order' => 20,
            ],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }

        $this->command->info('Created 20 departments.');
    }
}
