<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LocationType;

class LocationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locationTypes = [
            // Level 0 - Top Level
            [
                'name' => 'Campus',
                'category' => 'Educational',
                'hierarchy_level' => 0,
                'icon' => 'building-office-2',
                'suggestions' => []
            ],
            [
                'name' => 'Complex',
                'category' => 'Commercial',
                'hierarchy_level' => 0,
                'icon' => 'building-office',
                'suggestions' => []
            ],
            [
                'name' => 'Site',
                'category' => 'Industrial',
                'hierarchy_level' => 0,
                'icon' => 'map',
                'suggestions' => []
            ],

            // Level 1 - Buildings
            [
                'name' => 'Building',
                'category' => 'Structure',
                'hierarchy_level' => 1,
                'icon' => 'building',
                'suggestions' => []
            ],
            [
                'name' => 'Tower',
                'category' => 'Structure',
                'hierarchy_level' => 1,
                'icon' => 'building',
                'suggestions' => []
            ],
            [
                'name' => 'Warehouse',
                'category' => 'Storage',
                'hierarchy_level' => 1,
                'icon' => 'cube',
                'suggestions' => []
            ],
            [
                'name' => 'Facility',
                'category' => 'Utility',
                'hierarchy_level' => 1,
                'icon' => 'wrench-screwdriver',
                'suggestions' => []
            ],

            // Level 2 - Floors/Sections
            [
                'name' => 'Floor',
                'category' => 'Level',
                'hierarchy_level' => 2,
                'icon' => 'rectangle-stack',
                'suggestions' => []
            ],
            [
                'name' => 'Wing',
                'category' => 'Section',
                'hierarchy_level' => 2,
                'icon' => 'squares-2x2',
                'suggestions' => []
            ],
            [
                'name' => 'Section',
                'category' => 'Area',
                'hierarchy_level' => 2,
                'icon' => 'square-3-stack-3d',
                'suggestions' => []
            ],
            [
                'name' => 'Zone',
                'category' => 'Area',
                'hierarchy_level' => 2,
                'icon' => 'map-pin',
                'suggestions' => []
            ],
            [
                'name' => 'Area',
                'category' => 'Space',
                'hierarchy_level' => 2,
                'icon' => 'rectangle-group',
                'suggestions' => []
            ],

            // Level 3 - Rooms/Specific Locations
            [
                'name' => 'Room',
                'category' => 'Space',
                'hierarchy_level' => 3,
                'icon' => 'home',
                'suggestions' => []
            ],
            [
                'name' => 'Office',
                'category' => 'Workspace',
                'hierarchy_level' => 3,
                'icon' => 'briefcase',
                'suggestions' => []
            ],
            [
                'name' => 'Lab',
                'category' => 'Research',
                'hierarchy_level' => 3,
                'icon' => 'beaker',
                'suggestions' => []
            ],
            [
                'name' => 'Classroom',
                'category' => 'Educational',
                'hierarchy_level' => 3,
                'icon' => 'academic-cap',
                'suggestions' => []
            ],
            [
                'name' => 'Bay',
                'category' => 'Storage',
                'hierarchy_level' => 3,
                'icon' => 'archive-box',
                'suggestions' => []
            ],
            [
                'name' => 'Aisle',
                'category' => 'Pathway',
                'hierarchy_level' => 3,
                'icon' => 'arrows-right-left',
                'suggestions' => []
            ],
            [
                'name' => 'Slot',
                'category' => 'Position',
                'hierarchy_level' => 3,
                'icon' => 'square',
                'suggestions' => []
            ],
            [
                'name' => 'Station',
                'category' => 'Workpoint',
                'hierarchy_level' => 3,
                'icon' => 'computer-desktop',
                'suggestions' => []
            ],
            [
                'name' => 'Booth',
                'category' => 'Workspace',
                'hierarchy_level' => 3,
                'icon' => 'cube-transparent',
                'suggestions' => []
            ],
        ];

        foreach ($locationTypes as $type) {
            LocationType::create($type);
        }
    }
}