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
        if (AssetStatus::count() >= 10) {
            $this->command->info('Asset statuses already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding asset statuses...');
        
        $statuses = [
            ['name' => 'Active', 'color' => '#10B981', 'description' => 'Asset is operational and in use', 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Maintenance', 'color' => '#F59E0B', 'description' => 'Asset is under maintenance or repair', 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Inactive', 'color' => '#9CA3AF', 'description' => 'Asset is not currently in use', 'is_active' => true, 'sort_order' => 3],
            ['name' => 'Retired', 'color' => '#EF4444', 'description' => 'Asset is retired and no longer in service', 'is_active' => true, 'sort_order' => 4],
            ['name' => 'Archived', 'color' => '#6B7280', 'description' => 'Asset has been archived', 'is_active' => false, 'sort_order' => 5],
            ['name' => 'Pending', 'color' => '#3B82F6', 'description' => 'Asset is pending deployment', 'is_active' => true, 'sort_order' => 6],
            ['name' => 'In Transit', 'color' => '#8B5CF6', 'description' => 'Asset is being transferred', 'is_active' => true, 'sort_order' => 7],
            ['name' => 'Damaged', 'color' => '#DC2626', 'description' => 'Asset is damaged', 'is_active' => true, 'sort_order' => 8],
            ['name' => 'Lost', 'color' => '#7C2D12', 'description' => 'Asset is lost or missing', 'is_active' => true, 'sort_order' => 9],
            ['name' => 'Disposed', 'color' => '#44403C', 'description' => 'Asset has been disposed', 'is_active' => false, 'sort_order' => 10],
        ];

        foreach ($statuses as $status) {
            AssetStatus::create($status);
        }

        $this->command->info('Created ' . AssetStatus::count() . ' asset statuses.');
    }
} 