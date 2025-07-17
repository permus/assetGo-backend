<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LocationType;

class LocationTypeSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $locationTypes = [
            [
                'name' => 'Campus',
                'category' => 'Level 1',
                'icon' => 'building-office-2',
            ],
            [
                'name' => 'Building',
                'category' => 'Level 2',
                'icon' => 'building-office',
            ],
            [
                'name' => 'Floor',
                'category' => 'Level 3',
                'icon' => 'squares-2x2',
            ],
            [
                'name' => 'Room',
                'category' => 'Level 4',
                'icon' => 'home',
            ],
            [
                'name' => 'Office',
                'category' => 'Level 4',
                'icon' => 'briefcase',
            ],
            [
                'name' => 'Conference Room',
                'category' => 'Level 4',
                'icon' => 'users',
            ],
            [
                'name' => 'Storage',
                'category' => 'Level 4',
                'icon' => 'archive-box',
            ],
            [
                'name' => 'Warehouse',
                'category' => 'Level 2',
                'icon' => 'building-storefront',
            ],
            [
                'name' => 'Parking',
                'category' => 'Level 3',
                'icon' => 'truck',
            ],
            [
                'name' => 'Laboratory',
                'category' => 'Level 4',
                'icon' => 'beaker',
            ],
        ];

        foreach ($locationTypes as $type) {
            LocationType::create($type);
        }
    }
}