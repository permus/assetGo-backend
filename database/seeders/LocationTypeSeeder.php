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
                'name' => 'Community',
                'category' => 'Residential',
                'hierarchy_level' => 0,
                'icon' => 'https://unpkg.com/lucide-static/icons/home.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Campus',
                'category' => 'Educational',
                'hierarchy_level' => 0,
                'icon' => 'https://unpkg.com/lucide-static/icons/building-2.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Industrial Complex',
                'category' => 'Industrial',
                'hierarchy_level' => 0,
                'icon' => 'https://unpkg.com/lucide-static/icons/factory.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Shopping Complex',
                'category' => 'Commercial',
                'hierarchy_level' => 0,
                'icon' => 'https://unpkg.com/lucide-static/icons/shopping-cart.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Medical Campus',
                'category' => 'Medical',
                'hierarchy_level' => 0,
                'icon' => 'https://unpkg.com/lucide-static/icons/hospital.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Business Park',
                'category' => 'Commercial',
                'hierarchy_level' => 0,
                'icon' => 'https://unpkg.com/lucide-static/icons/briefcase.svg',
                'suggestions' => [],
            ],

            // Level 1 - Buildings
            [
                'name' => 'Office Building',
                'category' => 'Commercial',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/building.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Retail Store',
                'category' => 'Commercial',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/shopping-bag.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Data Center',
                'category' => 'Technical',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/server.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Warehouse',
                'category' => 'Industrial',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/package.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Factory',
                'category' => 'Industrial',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/settings.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'School',
                'category' => 'Institutional',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/graduation-cap.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Hospital',
                'category' => 'Institutional',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/hospital.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Hotel',
                'category' => 'Institutional',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/building.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Sports Facility',
                'category' => 'Recreational',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/trophy.svg',
                'suggestions' => [],
            ],
            [
                'name' => 'Residential Building',
                'category' => 'Residential',
                'hierarchy_level' => 1,
                'icon' => 'https://unpkg.com/lucide-static/icons/home.svg',
                'suggestions' => [],
            ],

            // Level 2 - Floors
            [
                'name' => 'Floor',
                'category' => 'Level',
                'hierarchy_level' => 2,
                'icon' => 'https://unpkg.com/lucide-static/icons/layers.svg',
                'suggestions' => [],
            ],

                // Level 3 - Rooms and Areas
            ['name' => 'Office', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/briefcase.svg', 'suggestions' => []],
            ['name' => 'Conference Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/presentation.svg', 'suggestions' => []],
            ['name' => 'Break Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/coffee.svg', 'suggestions' => []],
            ['name' => 'Reception', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/user.svg', 'suggestions' => []],
            ['name' => 'Lobby', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/building-2.svg', 'suggestions' => []],
            ['name' => 'Classroom', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/graduation-cap.svg', 'suggestions' => []],
            ['name' => 'Laboratory', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/flask-conical.svg', 'suggestions' => []],
            ['name' => 'Library', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/book-open.svg', 'suggestions' => []],
            ['name' => 'Gymnasium', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/dumbbell.svg', 'suggestions' => []],
            ['name' => 'Apartment', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/home.svg', 'suggestions' => []],
            ['name' => 'Server Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/server.svg', 'suggestions' => []],
            ['name' => 'Mechanical Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/settings.svg', 'suggestions' => []],
            ['name' => 'Utility Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/wrench.svg', 'suggestions' => []],
            ['name' => 'Electrical Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/zap.svg', 'suggestions' => []],
            ['name' => 'Equipment Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/tool.svg', 'suggestions' => []],
            ['name' => 'Storage Unit', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/archive.svg', 'suggestions' => []],
            ['name' => 'Storage Closet', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/archive.svg', 'suggestions' => []],
            ['name' => 'Workshop', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/hammer.svg', 'suggestions' => []],
            ['name' => 'Maintenance Shop', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/wrench.svg', 'suggestions' => []],
            ['name' => 'Warehouse', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/package.svg', 'suggestions' => []],
            ['name' => 'Cafeteria', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/utensils.svg', 'suggestions' => []],
            ['name' => 'Kitchen', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/utensils.svg', 'suggestions' => []],
            ['name' => 'Laundry Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/shirt.svg', 'suggestions' => []],
            ['name' => 'Mailroom', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/mail.svg', 'suggestions' => []],
            ['name' => 'First Aid Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/first-aid-kit.svg', 'suggestions' => []],
            ['name' => 'Security Office', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/shield.svg', 'suggestions' => []],
            ['name' => 'Fitness Center', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/dumbbell.svg', 'suggestions' => []],
            ['name' => 'Pool', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/waves.svg', 'suggestions' => []],
            ['name' => 'Spa', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/droplet.svg', 'suggestions' => []],
            ['name' => 'Game Room', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/gamepad-2.svg', 'suggestions' => []],
            ['name' => 'Lounge', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/sofa.svg', 'suggestions' => []],
            ['name' => 'Corridor', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/arrow-left-right.svg', 'suggestions' => []],
            ['name' => 'Elevator Lobby', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/move-vertical.svg', 'suggestions' => []],
            ['name' => 'Stairwell', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/stairs.svg', 'suggestions' => []],
            ['name' => 'Parking Lot', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/car.svg', 'suggestions' => []],
            ['name' => 'Outdoor Area', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/tree-deciduous.svg', 'suggestions' => []],
            ['name' => 'Garden', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/flower.svg', 'suggestions' => []],
            ['name' => 'Courtyard', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/square.svg', 'suggestions' => []],
            ['name' => 'Rooftop', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/building-2.svg', 'suggestions' => []],
            ['name' => 'Balcony', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/layout.svg', 'suggestions' => []],
            ['name' => 'Loading Dock', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/truck.svg', 'suggestions' => []],
            ['name' => 'Garage', 'category' => '', 'hierarchy_level' => 3, 'icon' => 'https://unpkg.com/lucide-static/icons/warehouse.svg', 'suggestions' => []],
        ];

        foreach ($locationTypes as $type) {
            LocationType::create($type);
        }
    }
}
