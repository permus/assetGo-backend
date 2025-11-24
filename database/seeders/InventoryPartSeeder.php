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
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        if (InventoryPart::where('company_id', $company->id)->count() >= 10) {
            $this->command->info('Inventory parts already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding inventory parts...');

        $faker = Faker::create();
        $categories = InventoryCategory::where('company_id', $company->id)->get();
        $user = User::where('company_id', $company->id)->first();

        // Use factory to create 12 inventory parts
        for ($i = 1; $i <= 12; $i++) {
            InventoryPart::create([
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'part_number' => 'PART-' . strtoupper($faker->bothify('####-???')),
                'name' => $faker->words(3, true),
                'description' => $faker->sentence(),
                'manufacturer' => $faker->company(),
                'maintenance_category' => $faker->randomElement(['Electrical', 'Mechanical', 'Hydraulic', 'Pneumatic', 'Electronics']),
                'uom' => $faker->randomElement(['PCS', 'KG', 'M', 'L', 'BOX', 'SET']),
                'unit_cost' => $faker->randomFloat(2, 5, 500),
                'category_id' => $categories->isNotEmpty() ? $categories->random()->id : null,
                'reorder_point' => $faker->numberBetween(5, 20),
                'reorder_qty' => $faker->numberBetween(20, 100),
                'minimum_stock' => $faker->numberBetween(5, 15),
                'maximum_stock' => $faker->numberBetween(100, 500),
                'is_consumable' => $faker->boolean(),
                'usage_tracking' => $faker->boolean(70),
                'status' => 'active',
                'abc_class' => $faker->randomElement(['A', 'B', 'C']),
            ]);
        }

        $this->command->info('Created ' . InventoryPart::where('company_id', $company->id)->count() . ' inventory parts.');
    }
}
