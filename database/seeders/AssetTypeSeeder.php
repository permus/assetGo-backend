<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LocationType;

class AssetTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $assetTypes = [
            [
                'name' => 'Fixed Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/layout-dashboard.svg',
            ],
            [
                'name' => 'Semi-Fixed Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/layout-dashboard.svg',
            ],
            [
                'name' => 'Mobile Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/layout-dashboard.svg',
            ],
            [
                'name' => 'Fleet Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/layout-dashboard.svg',
            ],
        ];

        foreach ($assetTypes as $type) {
            LocationType::create($type);
        }
    }
}
