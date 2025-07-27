<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetType;

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
                'icon' => 'https://unpkg.com/lucide-static/icons/building-2.svg',
            ],
            [
                'name' => 'Semi-Fixed Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/package.svg',
            ],
            [
                'name' => 'Mobile Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/smartphone.svg',
            ],
            [
                'name' => 'Fleet Assets',
                'icon' => 'https://unpkg.com/lucide-static/icons/truck.svg',
            ],
        ];

        foreach ($assetTypes as $type) {
            AssetType::create($type);
        }
    }
}
