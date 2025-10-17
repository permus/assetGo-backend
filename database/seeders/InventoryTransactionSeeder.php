<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryLocation;
use App\Models\InventoryPart;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) return;

        $parts = InventoryPart::where('company_id', $company->id)->limit(2)->get();
        $location = InventoryLocation::where('company_id', $company->id)->first();
        $user = User::where('company_id', $company->id)->first();

        if ($parts->count() == 0 || !$location) return;

        foreach ($parts as $part) {
            InventoryTransaction::create([
                'company_id' => $company->id,
                'part_id' => $part->id,
                'location_id' => $location->id,
                'type' => 'receipt',
                'quantity' => rand(10, 50),
                'unit_cost' => $part->unit_cost,
                'total_cost' => $part->unit_cost * rand(10, 50),
                'user_id' => $user?->id,
            ]);
        }
        $this->command->info('Created inventory transactions.');
    }
}
