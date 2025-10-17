<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\InventoryCategory;
use App\Models\InventoryPart;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class InventoryPartSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company || InventoryPart::count() > 0) return;

        $faker = Faker::create();
        $category = InventoryCategory::where('company_id', $company->id)->first();
        $user = User::where('company_id', $company->id)->first();

        $parts = [
            ['name' => 'Laptop Battery', 'unit_cost' => 89.99],
            ['name' => 'Network Cable CAT6', 'unit_cost' => 2.50],
            ['name' => 'RAM Module 16GB', 'unit_cost' => 75.00],
        ];

        foreach ($parts as $part) {
            InventoryPart::create([
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'part_number' => 'PART-' . $faker->numerify('####'),
                'name' => $part['name'],
                'description' => $faker->sentence(),
                'uom' => 'each',
                'unit_cost' => $part['unit_cost'],
                'category_id' => $category?->id,
                'reorder_point' => 10,
                'reorder_qty' => 20,
                'status' => 'active',
            ]);
        }
        $this->command->info('Created inventory parts.');
    }
}
