<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();
        // Uncomment the following line to seed the LocationTypeSeeder
         $this->call(LocationTypeSeeder::class);
        $this->call(AssetCategoriesSeeder::class);
        $this->call(AssetTypeSeeder::class);
        $this->call(AssetStatusSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(WorkOrderSeeder::class);
        $this->call(SupplierSeeder::class);

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
