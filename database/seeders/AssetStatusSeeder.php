<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetStatus;

class AssetStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate the table to avoid duplicates
        AssetStatus::truncate();
        
        $statuses = [
            [
                'name' => 'Active',
                'color' => '#10B981', // Green
                'description' => 'Asset is operational and in use',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Maintenance',
                'color' => '#F59E0B', // Orange/Yellow
                'description' => 'Asset is under maintenance or repair',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Inactive',
                'color' => '#9CA3AF', // Grey
                'description' => 'Asset is not currently in use',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Retired',
                'color' => '#EF4444', // Red
                'description' => 'Asset is retired and no longer in service',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Archived',
                'color' => '#6B7280', // Dark grey
                'description' => 'Asset has been archived',
                'is_active' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($statuses as $status) {
            AssetStatus::create($status);
        }
    }
} 