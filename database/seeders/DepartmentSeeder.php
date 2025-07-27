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
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
