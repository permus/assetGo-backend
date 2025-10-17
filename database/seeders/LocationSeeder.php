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

        $mainBuilding = Location::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'location_type_id' => $building?->id ?? 1,
            'name' => 'Main Office Building',
            'slug' => 'main-office-building',
            'address' => '123 Business Street, New York, NY 10001',
        ]);

        $floor1 = Location::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'location_type_id' => $floor?->id ?? 2,
            'parent_id' => $mainBuilding->id,
            'name' => 'First Floor',
            'slug' => 'first-floor',
        ]);

        Location::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'location_type_id' => $room?->id ?? 3,
            'parent_id' => $floor1->id,
            'name' => 'Reception',
            'slug' => 'reception',
        ]);

        Location::create([
            'company_id' => $company->id,
            'user_id' => $user?->id,
            'location_type_id' => $room?->id ?? 3,
            'parent_id' => $floor1->id,
            'name' => 'IT Lab',
            'slug' => 'it-lab',
        ]);

        $this->command->info('Created ' . Location::where('company_id', $company->id)->count() . ' locations.');
    }
}
