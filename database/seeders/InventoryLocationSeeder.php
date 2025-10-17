<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryLocation;
use Illuminate\Database\Seeder;

class InventoryLocationSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company || InventoryLocation::count() > 0) return;

        $locations = [
            ['company_id' => $company->id, 'name' => 'Main Storage', 'code' => 'MS-01'],
            ['company_id' => $company->id, 'name' => 'Warehouse A', 'code' => 'WH-A'],
        ];

        foreach ($locations as $location) {
            InventoryLocation::create($location);
        }
        $this->command->info('Created inventory locations.');
    }
}
