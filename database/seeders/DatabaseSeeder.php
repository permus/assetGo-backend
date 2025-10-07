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
        // Step 1: Seed lookup/reference tables (no dependencies)
        $this->call(LocationTypeSeeder::class);
        $this->call(AssetCategoriesSeeder::class);
        $this->call(AssetTypeSeeder::class);
        $this->call(AssetStatusSeeder::class);
        $this->call(ModuleDefinitionsSeeder::class);
        
        // Step 2: Seed Work Order meta tables (before WorkOrderSeeder)
        $this->call(WorkOrderStatusSeeder::class);
        $this->call(WorkOrderPrioritySeeder::class);
        $this->call(WorkOrderCategorySeeder::class);
        
        // Step 3: Seed Company, Users, Roles (SupplierSeeder creates these if missing)
        $this->call(SupplierSeeder::class); // Creates company & user if needed
        $this->call(RoleSeeder::class);
        $this->call(DepartmentSeeder::class);
        
        // Step 4: Seed Work Orders (requires company, users, assets, locations)
        // Note: WorkOrderSeeder will skip if no companies exist
        $this->call(WorkOrderSeeder::class);
        
        // Optional: Seed test data for reports
        // $this->call(ReportsTestDataSeeder::class);
    }
}
