<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company || InventoryCategory::count() > 0) return;

        $categories = [
            ['company_id' => $company->id, 'name' => 'Spare Parts', 'description' => 'Replacement parts'],
            ['company_id' => $company->id, 'name' => 'Consumables', 'description' => 'Consumable items'],
            ['company_id' => $company->id, 'name' => 'Tools', 'description' => 'Maintenance tools'],
        ];

        foreach ($categories as $category) {
            InventoryCategory::create($category);
        }
        $this->command->info('Created inventory categories.');
    }
}
