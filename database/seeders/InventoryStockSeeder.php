<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryLocation;
use App\Models\InventoryPart;
use App\Models\InventoryStock;
use Illuminate\Database\Seeder;

class InventoryStockSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company || InventoryStock::count() > 0) return;

        $parts = InventoryPart::where('company_id', $company->id)->get();
        $location = InventoryLocation::where('company_id', $company->id)->first();

        if ($parts->count() == 0 || !$location) return;

        foreach ($parts as $part) {
            $onHand = rand(10, 100);
            $reserved = rand(0, 10);
            
            InventoryStock::create([
                'company_id' => $company->id,
                'part_id' => $part->id,
                'location_id' => $location->id,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $onHand - $reserved,
                'average_cost' => $part->unit_cost,
            ]);
        }
        $this->command->info('Created inventory stocks.');
    }
}
