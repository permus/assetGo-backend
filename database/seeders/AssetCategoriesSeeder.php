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
            'HVAC System',
            'Plumbing System',
            'Fire Safety',
            'Fixtures',
            'Gardens & Landscaping',
            'Elevator System',
            'Lighting System',
            'Electrical System',
            'Security System',
            'Camera System',
            'Passenger Vehicle',
            'Commercial Vehicle',
            'Heavy Machinery',
            'Production Equipment',
            'Quality Control',
            'Material Handling',
            'Computer Hardware',
            'Mobile Devices',
            'Printing Equipment',
            'Server Equipment',
            'Network Equipment',
            'Storage Systems',
            'Backup Systems',
            'Office Furniture',
            'Office Equipment',
            'Safety Equipment',
            'Compliance Tools',
            'Other',
        ];

        foreach ($categories as $name) {
            AssetCategory::create(['name' => $name]);
        }
    }
}
