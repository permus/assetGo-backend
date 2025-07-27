<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AssetCategory;

class AssetCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate the table to avoid duplicates
        AssetCategory::truncate();
        
        $categories = [
            [
                'name' => 'HVAC System',
                'icon' => 'https://unpkg.com/lucide-static/icons/thermometer.svg',
            ],
            [
                'name' => 'Plumbing System',
                'icon' => 'https://unpkg.com/lucide-static/icons/droplets.svg',
            ],
            [
                'name' => 'Fire Safety',
                'icon' => 'https://unpkg.com/lucide-static/icons/flame.svg',
            ],
            [
                'name' => 'Fixtures',
                'icon' => 'https://unpkg.com/lucide-static/icons/lamp.svg',
            ],
            [
                'name' => 'Gardens & Landscaping',
                'icon' => 'https://unpkg.com/lucide-static/icons/tree-pine.svg',
            ],
            [
                'name' => 'Elevator System',
                'icon' => 'https://unpkg.com/lucide-static/icons/move-vertical.svg',
            ],
            [
                'name' => 'Lighting System',
                'icon' => 'https://unpkg.com/lucide-static/icons/lightbulb.svg',
            ],
            [
                'name' => 'Electrical System',
                'icon' => 'https://unpkg.com/lucide-static/icons/zap.svg',
            ],
            [
                'name' => 'Security System',
                'icon' => 'https://unpkg.com/lucide-static/icons/shield.svg',
            ],
            [
                'name' => 'Camera System',
                'icon' => 'https://unpkg.com/lucide-static/icons/video.svg',
            ],
            [
                'name' => 'Passenger Vehicle',
                'icon' => 'https://unpkg.com/lucide-static/icons/car.svg',
            ],
            [
                'name' => 'Commercial Vehicle',
                'icon' => 'https://unpkg.com/lucide-static/icons/truck.svg',
            ],
            [
                'name' => 'Heavy Machinery',
                'icon' => 'https://unpkg.com/lucide-static/icons/drill.svg',
            ],
            [
                'name' => 'Production Equipment',
                'icon' => 'https://unpkg.com/lucide-static/icons/factory.svg',
            ],
            [
                'name' => 'Quality Control',
                'icon' => 'https://unpkg.com/lucide-static/icons/check-circle.svg',
            ],
            [
                'name' => 'Material Handling',
                'icon' => 'https://unpkg.com/lucide-static/icons/forklift.svg',
            ],
            [
                'name' => 'Computer Hardware',
                'icon' => 'https://unpkg.com/lucide-static/icons/monitor.svg',
            ],
            [
                'name' => 'Mobile Devices',
                'icon' => 'https://unpkg.com/lucide-static/icons/smartphone.svg',
            ],
            [
                'name' => 'Printing Equipment',
                'icon' => 'https://unpkg.com/lucide-static/icons/printer.svg',
            ],
            [
                'name' => 'Server Equipment',
                'icon' => 'https://unpkg.com/lucide-static/icons/server.svg',
            ],
            [
                'name' => 'Network Equipment',
                'icon' => 'https://unpkg.com/lucide-static/icons/wifi.svg',
            ],
            [
                'name' => 'Storage Systems',
                'icon' => 'https://unpkg.com/lucide-static/icons/hard-drive.svg',
            ],
            [
                'name' => 'Backup Systems',
                'icon' => 'https://unpkg.com/lucide-static/icons/archive.svg',
            ],
            [
                'name' => 'Office Furniture',
                'icon' => 'https://unpkg.com/lucide-static/icons/chair.svg',
            ],
            [
                'name' => 'Office Equipment',
                'icon' => 'https://unpkg.com/lucide-static/icons/briefcase.svg',
            ],
            [
                'name' => 'Safety Equipment',
                'icon' => 'https://unpkg.com/lucide-static/icons/helmet.svg',
            ],
            [
                'name' => 'Compliance Tools',
                'icon' => 'https://unpkg.com/lucide-static/icons/clipboard-check.svg',
            ],
            [
                'name' => 'Other',
                'icon' => 'https://unpkg.com/lucide-static/icons/package.svg',
            ],
        ];

        foreach ($categories as $category) {
            AssetCategory::create($category);
        }
    }
}
