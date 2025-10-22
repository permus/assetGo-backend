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
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (InventoryCategory::where('company_id', $company->id)->count() >= 20) {
            $this->command->info('Inventory categories already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding inventory categories...');

        $categories = [
            ['company_id' => $company->id, 'name' => 'Spare Parts', 'description' => 'Replacement parts', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Consumables', 'description' => 'Consumable items', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Tools', 'description' => 'Maintenance tools', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Electrical Components', 'description' => 'Electrical parts and components', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Mechanical Parts', 'description' => 'Mechanical components', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Hydraulic Components', 'description' => 'Hydraulic parts and systems', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Pneumatic Parts', 'description' => 'Pneumatic components', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Lubricants & Oils', 'description' => 'Lubrication supplies', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Fasteners', 'description' => 'Bolts, nuts, screws, etc.', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Bearings', 'description' => 'Various types of bearings', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Filters', 'description' => 'Air, oil, and fuel filters', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Belts & Chains', 'description' => 'Drive belts and chains', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Seals & Gaskets', 'description' => 'Sealing components', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Safety Equipment', 'description' => 'Personal protective equipment', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Hand Tools', 'description' => 'Manual tools', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Power Tools', 'description' => 'Electric and pneumatic tools', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Cleaning Supplies', 'description' => 'Cleaning materials', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Adhesives & Sealants', 'description' => 'Glues and sealants', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Welding Supplies', 'description' => 'Welding materials and consumables', 'is_active' => true],
            ['company_id' => $company->id, 'name' => 'Paint & Coatings', 'description' => 'Paints and surface coatings', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            InventoryCategory::create($category);
        }

        $this->command->info('Created ' . InventoryCategory::where('company_id', $company->id)->count() . ' inventory categories.');
    }
}
