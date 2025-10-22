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
        if (AssetStatus::count() >= 20) {
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
            ['name' => 'Stolen', 'color' => '#450A0A', 'description' => 'Asset has been stolen', 'is_active' => true, 'sort_order' => 10],
            ['name' => 'On Loan', 'color' => '#059669', 'description' => 'Asset is loaned to another party', 'is_active' => true, 'sort_order' => 11],
            ['name' => 'Reserved', 'color' => '#7C3AED', 'description' => 'Asset is reserved for use', 'is_active' => true, 'sort_order' => 12],
            ['name' => 'Calibration', 'color' => '#CA8A04', 'description' => 'Asset is undergoing calibration', 'is_active' => true, 'sort_order' => 13],
            ['name' => 'Testing', 'color' => '#0891B2', 'description' => 'Asset is being tested', 'is_active' => true, 'sort_order' => 14],
            ['name' => 'Quarantine', 'color' => '#BE123C', 'description' => 'Asset is in quarantine', 'is_active' => true, 'sort_order' => 15],
            ['name' => 'Disposed', 'color' => '#44403C', 'description' => 'Asset has been disposed', 'is_active' => false, 'sort_order' => 16],
            ['name' => 'Sold', 'color' => '#65A30D', 'description' => 'Asset has been sold', 'is_active' => false, 'sort_order' => 17],
            ['name' => 'Donated', 'color' => '#16A34A', 'description' => 'Asset has been donated', 'is_active' => false, 'sort_order' => 18],
            ['name' => 'Scrapped', 'color' => '#57534E', 'description' => 'Asset has been scrapped', 'is_active' => false, 'sort_order' => 19],
            ['name' => 'Under Review', 'color' => '#2563EB', 'description' => 'Asset is under review', 'is_active' => true, 'sort_order' => 20],
        ];

        foreach ($statuses as $status) {
            AssetStatus::create($status);
        }

        $this->command->info('Created ' . AssetStatus::count() . ' asset statuses.');
    }
} 