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
            ['name' => 'Community', 'category' => 'Residential', 'hierarchy_level' => 0, 'icon' => 'home-modern', 'suggestions' => []],
            ['name' => 'Campus', 'category' => 'Educational', 'hierarchy_level' => 0, 'icon' => 'building-office-2', 'suggestions' => []],
            ['name' => 'Industrial Complex', 'category' => 'Industrial', 'hierarchy_level' => 0, 'icon' => 'factory', 'suggestions' => []],
            ['name' => 'Shopping Complex', 'category' => 'Commercial', 'hierarchy_level' => 0, 'icon' => 'shopping-cart', 'suggestions' => []],
            ['name' => 'Medical Campus', 'category' => 'Medical', 'hierarchy_level' => 0, 'icon' => 'hospital', 'suggestions' => []],
            ['name' => 'Business Park', 'category' => 'Commercial', 'hierarchy_level' => 0, 'icon' => 'briefcase', 'suggestions' => []],

            // Level 1 - Buildings
            ['name' => 'Office Building', 'category' => 'Commercial', 'hierarchy_level' => 1, 'icon' => 'building', 'suggestions' => []],
            ['name' => 'Retail Store', 'category' => 'Commercial', 'hierarchy_level' => 1, 'icon' => 'shopping-bag', 'suggestions' => []],
            ['name' => 'Data Center', 'category' => 'Technical', 'hierarchy_level' => 1, 'icon' => 'server', 'suggestions' => []],
            ['name' => 'Warehouse', 'category' => 'Industrial', 'hierarchy_level' => 1, 'icon' => 'cube', 'suggestions' => []],
            ['name' => 'Factory', 'category' => 'Industrial', 'hierarchy_level' => 1, 'icon' => 'cog', 'suggestions' => []],
            ['name' => 'School', 'category' => 'Institutional', 'hierarchy_level' => 1, 'icon' => 'academic-cap', 'suggestions' => []],
            ['name' => 'Hospital', 'category' => 'Institutional', 'hierarchy_level' => 1, 'icon' => 'hospital', 'suggestions' => []],
            ['name' => 'Hotel', 'category' => 'Institutional', 'hierarchy_level' => 1, 'icon' => 'building', 'suggestions' => []],
            ['name' => 'Sports Facility', 'category' => 'Recreational', 'hierarchy_level' => 1, 'icon' => 'trophy', 'suggestions' => []],
            ['name' => 'Residential Building', 'category' => 'Residential', 'hierarchy_level' => 1, 'icon' => 'home', 'suggestions' => []],

            // Level 2 - Floors
            ['name' => 'Floor', 'category' => 'Level', 'hierarchy_level' => 2, 'icon' => 'rectangle-stack', 'suggestions' => []],

            // Level 3 - Rooms and Areas
            ['name' => 'Office', 'category' => 'Workspace', 'hierarchy_level' => 3, 'icon' => 'briefcase', 'suggestions' => []],
            ['name' => 'Conference Room', 'category' => 'Workspace', 'hierarchy_level' => 3, 'icon' => 'presentation-chart-bar', 'suggestions' => []],
            ['name' => 'Break Room', 'category' => 'Workspace', 'hierarchy_level' => 3, 'icon' => 'coffee', 'suggestions' => []],
            ['name' => 'Reception', 'category' => 'Workspace', 'hierarchy_level' => 3, 'icon' => 'user-circle', 'suggestions' => []],
            ['name' => 'Lobby', 'category' => 'Workspace', 'hierarchy_level' => 3, 'icon' => 'building', 'suggestions' => []],
            ['name' => 'Classroom', 'category' => 'Educational', 'hierarchy_level' => 3, 'icon' => 'academic-cap', 'suggestions' => []],
            ['name' => 'Laboratory', 'category' => 'Research', 'hierarchy_level' => 3, 'icon' => 'beaker', 'suggestions' => []],
            ['name' => 'Library', 'category' => 'Educational', 'hierarchy_level' => 3, 'icon' => 'book-open', 'suggestions' => []],
            ['name' => 'Gymnasium', 'category' => 'Recreational', 'hierarchy_level' => 3, 'icon' => 'dumbbell', 'suggestions' => []],
            ['name' => 'Apartment', 'category' => 'Residential', 'hierarchy_level' => 3, 'icon' => 'home', 'suggestions' => []],
            ['name' => 'Server Room', 'category' => 'Technical', 'hierarchy_level' => 3, 'icon' => 'server', 'suggestions' => []],
            ['name' => 'Mechanical Room', 'category' => 'Technical', 'hierarchy_level' => 3, 'icon' => 'cog', 'suggestions' => []],
            ['name' => 'Utility Room', 'category' => 'Technical', 'hierarchy_level' => 3, 'icon' => 'wrench', 'suggestions' => []],
            ['name' => 'Electrical Room', 'category' => 'Technical', 'hierarchy_level' => 3, 'icon' => 'bolt', 'suggestions' => []],
            ['name' => 'Equipment Room', 'category' => 'Technical', 'hierarchy_level' => 3, 'icon' => 'toolbox', 'suggestions' => []],
            ['name' => 'Storage Unit', 'category' => 'Storage', 'hierarchy_level' => 3, 'icon' => 'archive-box', 'suggestions' => []],
            ['name' => 'Storage Closet', 'category' => 'Storage', 'hierarchy_level' => 3, 'icon' => 'archive-box', 'suggestions' => []],
            ['name' => 'Workshop', 'category' => 'Work Area', 'hierarchy_level' => 3, 'icon' => 'hammer', 'suggestions' => []],
            ['name' => 'Maintenance Shop', 'category' => 'Work Area', 'hierarchy_level' => 3, 'icon' => 'wrench', 'suggestions' => []],
            ['name' => 'Warehouse', 'category' => 'Storage', 'hierarchy_level' => 3, 'icon' => 'cube', 'suggestions' => []],
            ['name' => 'Cafeteria', 'category' => 'Common Area', 'hierarchy_level' => 3, 'icon' => 'utensils', 'suggestions' => []],
            ['name' => 'Kitchen', 'category' => 'Common Area', 'hierarchy_level' => 3, 'icon' => 'utensils', 'suggestions' => []],
            ['name' => 'Laundry Room', 'category' => 'Common Area', 'hierarchy_level' => 3, 'icon' => 'tshirt', 'suggestions' => []],
            ['name' => 'Mailroom', 'category' => 'Common Area', 'hierarchy_level' => 3, 'icon' => 'envelope', 'suggestions' => []],
            ['name' => 'First Aid Room', 'category' => 'Common Area', 'hierarchy_level' => 3, 'icon' => 'first-aid', 'suggestions' => []],
            ['name' => 'Security Office', 'category' => 'Common Area', 'hierarchy_level' => 3, 'icon' => 'shield', 'suggestions' => []],
            ['name' => 'Fitness Center', 'category' => 'Amenities', 'hierarchy_level' => 3, 'icon' => 'dumbbell', 'suggestions' => []],
            ['name' => 'Pool', 'category' => 'Amenities', 'hierarchy_level' => 3, 'icon' => 'swimmer', 'suggestions' => []],
            ['name' => 'Spa', 'category' => 'Amenities', 'hierarchy_level' => 3, 'icon' => 'spa', 'suggestions' => []],
            ['name' => 'Game Room', 'category' => 'Amenities', 'hierarchy_level' => 3, 'icon' => 'gamepad', 'suggestions' => []],
            ['name' => 'Lounge', 'category' => 'Amenities', 'hierarchy_level' => 3, 'icon' => 'couch', 'suggestions' => []],
            ['name' => 'Corridor', 'category' => 'Circulation', 'hierarchy_level' => 3, 'icon' => 'arrows-right-left', 'suggestions' => []],
            ['name' => 'Elevator Lobby', 'category' => 'Circulation', 'hierarchy_level' => 3, 'icon' => 'elevator', 'suggestions' => []],
            ['name' => 'Stairwell', 'category' => 'Circulation', 'hierarchy_level' => 3, 'icon' => 'stairs', 'suggestions' => []],
            ['name' => 'Parking Lot', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'car', 'suggestions' => []],
            ['name' => 'Outdoor Area', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'tree', 'suggestions' => []],
            ['name' => 'Garden', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'flower', 'suggestions' => []],
            ['name' => 'Courtyard', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'square', 'suggestions' => []],
            ['name' => 'Rooftop', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'building', 'suggestions' => []],
            ['name' => 'Balcony', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'balcony', 'suggestions' => []],
            ['name' => 'Loading Dock', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'truck', 'suggestions' => []],
            ['name' => 'Garage', 'category' => 'Outdoor', 'hierarchy_level' => 3, 'icon' => 'garage', 'suggestions' => []],
        ];

        LocationType::insert($locationTypes);
    }
}
