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
                'suggestions' => [
                    'max_children' => 50,
                    'allowed_child_types' => ['Building', 'Facility'],
                    'required_fields' => ['name', 'address'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Complex',
                'category' => 'Commercial',
                'hierarchy_level' => 0,
                'icon' => 'building-office',
                'suggestions' => [
                    'max_children' => 20,
                    'allowed_child_types' => ['Building', 'Tower'],
                    'required_fields' => ['name', 'address'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Site',
                'category' => 'Industrial',
                'hierarchy_level' => 0,
                'icon' => 'map',
                'suggestions' => [
                    'max_children' => 30,
                    'allowed_child_types' => ['Building', 'Warehouse', 'Facility'],
                    'required_fields' => ['name', 'address'],
                    'optional_fields' => ['description']
                ]
            ],

            // Level 1 - Buildings
            [
                'name' => 'Building',
                'category' => 'Structure',
                'hierarchy_level' => 1,
                'icon' => 'building',
                'suggestions' => [
                    'max_children' => 100,
                    'allowed_child_types' => ['Floor', 'Wing'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['address', 'description']
                ]
            ],
            [
                'name' => 'Tower',
                'category' => 'Structure',
                'hierarchy_level' => 1,
                'icon' => 'building',
                'suggestions' => [
                    'max_children' => 50,
                    'allowed_child_types' => ['Floor'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Warehouse',
                'category' => 'Storage',
                'hierarchy_level' => 1,
                'icon' => 'cube',
                'suggestions' => [
                    'max_children' => 20,
                    'allowed_child_types' => ['Section', 'Zone'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Facility',
                'category' => 'Utility',
                'hierarchy_level' => 1,
                'icon' => 'wrench-screwdriver',
                'suggestions' => [
                    'max_children' => 30,
                    'allowed_child_types' => ['Room', 'Area'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],

            // Level 2 - Floors/Sections
            [
                'name' => 'Floor',
                'category' => 'Level',
                'hierarchy_level' => 2,
                'icon' => 'rectangle-stack',
                'suggestions' => [
                    'max_children' => 200,
                    'allowed_child_types' => ['Room', 'Office', 'Lab', 'Classroom'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Wing',
                'category' => 'Section',
                'hierarchy_level' => 2,
                'icon' => 'squares-2x2',
                'suggestions' => [
                    'max_children' => 50,
                    'allowed_child_types' => ['Room', 'Office'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Section',
                'category' => 'Area',
                'hierarchy_level' => 2,
                'icon' => 'square-3-stack-3d',
                'suggestions' => [
                    'max_children' => 30,
                    'allowed_child_types' => ['Bay', 'Aisle'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Zone',
                'category' => 'Area',
                'hierarchy_level' => 2,
                'icon' => 'map-pin',
                'suggestions' => [
                    'max_children' => 40,
                    'allowed_child_types' => ['Bay', 'Slot'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Area',
                'category' => 'Space',
                'hierarchy_level' => 2,
                'icon' => 'rectangle-group',
                'suggestions' => [
                    'max_children' => 25,
                    'allowed_child_types' => ['Station', 'Booth'],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],

            // Level 3 - Rooms/Specific Locations
            [
                'name' => 'Room',
                'category' => 'Space',
                'hierarchy_level' => 3,
                'icon' => 'home',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Office',
                'category' => 'Workspace',
                'hierarchy_level' => 3,
                'icon' => 'briefcase',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Lab',
                'category' => 'Research',
                'hierarchy_level' => 3,
                'icon' => 'beaker',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Classroom',
                'category' => 'Educational',
                'hierarchy_level' => 3,
                'icon' => 'academic-cap',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Bay',
                'category' => 'Storage',
                'hierarchy_level' => 3,
                'icon' => 'archive-box',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Aisle',
                'category' => 'Pathway',
                'hierarchy_level' => 3,
                'icon' => 'arrows-right-left',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Slot',
                'category' => 'Position',
                'hierarchy_level' => 3,
                'icon' => 'square',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Station',
                'category' => 'Workpoint',
                'hierarchy_level' => 3,
                'icon' => 'computer-desktop',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
            [
                'name' => 'Booth',
                'category' => 'Workspace',
                'hierarchy_level' => 3,
                'icon' => 'cube-transparent',
                'suggestions' => [
                    'max_children' => 0,
                    'allowed_child_types' => [],
                    'required_fields' => ['name'],
                    'optional_fields' => ['description']
                ]
            ],
        ];

        foreach ($locationTypes as $type) {
            LocationType::create($type);
        }
    }
}