<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) return;

        if (Asset::where('company_id', $company->id)->count() > 0) {
            $this->command->info('Assets already exist. Skipping.');
            return;
        }

        $this->command->info('Seeding assets...');

        $faker = Faker::create();
        $users = User::where('company_id', $company->id)->get();
        $locations = Location::where('company_id', $company->id)->get();
        $departments = Department::where('company_id', $company->id)->get();
        $categories = AssetCategory::all();

        $assetTemplates = [
            ['name' => 'Dell Laptop XPS 15', 'manufacturer' => 'Dell', 'model' => 'XPS 15 9500'],
            ['name' => 'HP Desktop Computer', 'manufacturer' => 'HP', 'model' => 'EliteDesk 800'],
            ['name' => 'iPhone 13 Pro', 'manufacturer' => 'Apple', 'model' => 'iPhone 13 Pro'],
            ['name' => 'Samsung Monitor 27"', 'manufacturer' => 'Samsung', 'model' => 'S27R750'],
            ['name' => 'Herman Miller Chair', 'manufacturer' => 'Herman Miller', 'model' => 'Aeron'],
        ];

        $counter = 1;
        foreach ($assetTemplates as $template) {
            for ($i = 1; $i <= 2; $i++) {
                Asset::create([
                    'asset_id' => 'AST-' . str_pad($counter, 6, '0', STR_PAD_LEFT),
                    'name' => $template['name'] . ' #' . $i,
                    'description' => $faker->sentence(),
                    'category_id' => $categories->count() > 0 ? $categories->random()->id : null,
                    'serial_number' => strtoupper($faker->bothify('SN-????-####')),
                    'model' => $template['model'],
                    'manufacturer' => $template['manufacturer'],
                    'brand' => $template['manufacturer'],
                    'purchase_date' => $faker->dateTimeBetween('-2 years', '-1 month'),
                    'purchase_price' => $faker->randomFloat(2, 100, 5000),
                    'depreciation' => $faker->randomFloat(2, 10, 500),
                    'depreciation_life' => 36,
                    'location_id' => $locations->count() > 0 ? $locations->random()->id : null,
                    'department_id' => $departments->count() > 0 ? $departments->random()->id : null,
                    'user_id' => $users->count() > 0 ? $users->random()->id : null,
                    'company_id' => $company->id,
                    'warranty' => $faker->randomElement(['1 Year', '2 Years', '3 Years']),
                    'health_score' => $faker->randomFloat(2, 70, 100),
                    'status' => 'active',
                    'is_active' => 1,
                ]);
                $counter++;
            }
        }

        $this->command->info('Created ' . Asset::where('company_id', $company->id)->count() . ' assets.');
    }
}
