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
        if (AssetType::count() >= 11) {
            $this->command->info('Asset types already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding asset types...');
        
        $assetTypes = [
            ['name' => 'Fixed Assets', 'icon' => 'https://unpkg.com/lucide-static/icons/building-2.svg'],
            ['name' => 'Semi-Fixed Assets', 'icon' => 'https://unpkg.com/lucide-static/icons/package.svg'],
            ['name' => 'Mobile Assets', 'icon' => 'https://unpkg.com/lucide-static/icons/smartphone.svg'],
            ['name' => 'Fleet Assets', 'icon' => 'https://unpkg.com/lucide-static/icons/truck.svg'],
            ['name' => 'IT Equipment', 'icon' => 'https://unpkg.com/lucide-static/icons/laptop.svg'],
            ['name' => 'Office Equipment', 'icon' => 'https://unpkg.com/lucide-static/icons/printer.svg'],
            ['name' => 'Furniture', 'icon' => 'https://unpkg.com/lucide-static/icons/chair.svg'],
            ['name' => 'Machinery', 'icon' => 'https://unpkg.com/lucide-static/icons/drill.svg'],
            ['name' => 'Tools', 'icon' => 'https://unpkg.com/lucide-static/icons/wrench.svg'],
            ['name' => 'Vehicles', 'icon' => 'https://unpkg.com/lucide-static/icons/car.svg'],
            ['name' => 'Medical Equipment', 'icon' => 'https://unpkg.com/lucide-static/icons/stethoscope.svg'],
        ];

        foreach ($assetTypes as $type) {
            AssetType::create($type);
        }

        $this->command->info('Created ' . AssetType::count() . ' asset types.');
    }
}
