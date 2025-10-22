<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) return;

        if (Location::where('company_id', $company->id)->count() > 0) {
            $this->command->info('Locations already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding locations...');
        $user = User::where('company_id', $company->id)->first();
        
        $building = LocationType::where('name', 'Building')->first();
        $floor = LocationType::where('name', 'Floor')->first();
        $room = LocationType::where('name', 'Room')->first();

        // Create Main Building
        $mainBuilding = Location::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'location_type_id' => $building?->id ?? 1,
            'name' => 'Main Office Building',
            'slug' => 'main-office-building',
            'address' => '123 Business Street, New York, NY 10001',
        ]);

        // Create floors and rooms
        $floors = ['First Floor', 'Second Floor', 'Third Floor'];
        $roomTypes = ['Office', 'Conference Room', 'Storage', 'Lab', 'Break Room', 'Server Room', 'Workshop'];
        
        foreach ($floors as $index => $floorName) {
            $floor = Location::create([
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'location_type_id' => $floor?->id ?? 2,
                'parent_id' => $mainBuilding->id,
                'name' => $floorName,
                'slug' => strtolower(str_replace(' ', '-', $floorName)),
            ]);
            
            // Create rooms for each floor
            for ($i = 1; $i <= 3; $i++) {
                $roomType = $roomTypes[array_rand($roomTypes)];
                Location::create([
                    'company_id' => $company->id,
                    'user_id' => $user?->id,
                    'location_type_id' => $room?->id ?? 3,
                    'parent_id' => $floor->id,
                    'name' => $roomType . ' ' . (($index * 100) + $i),
                    'slug' => strtolower(str_replace(' ', '-', $roomType . '-' . (($index * 100) + $i))),
                ]);
            }
        }

        // Create Warehouse Building
        $warehouse = Location::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'location_type_id' => $building?->id ?? 1,
            'name' => 'Warehouse',
            'slug' => 'warehouse',
            'address' => '456 Industrial Avenue, New York, NY 10002',
        ]);

        // Create warehouse sections
        $sections = ['Section A', 'Section B', 'Section C'];
        $aisleCounter = 1;
        foreach ($sections as $sectionName) {
            $section = Location::create([
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'location_type_id' => $floor?->id ?? 2,
                'parent_id' => $warehouse->id,
                'name' => $sectionName,
                'slug' => strtolower(str_replace(' ', '-', $sectionName)),
            ]);
            
            // Create aisles in each section
            for ($i = 1; $i <= 2; $i++) {
                Location::create([
                    'company_id' => $company->id,
                    'user_id' => $user?->id,
                    'location_type_id' => $room?->id ?? 3,
                    'parent_id' => $section->id,
                    'name' => 'Aisle ' . $aisleCounter,
                    'slug' => strtolower('aisle-' . $aisleCounter),
                ]);
                $aisleCounter++;
            }
        }

        $this->command->info('Created ' . Location::where('company_id', $company->id)->count() . ' locations.');
    }
}
