<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('========================================');
        $this->command->info('Starting database seeding...');
        $this->command->info('========================================');

        // Phase 1: Lookup/Reference Data
        $this->command->info('Phase 1: Lookup tables...');
        $this->call(LocationTypeSeeder::class);
        $this->call(AssetCategoriesSeeder::class);
        $this->call(AssetTypeSeeder::class);
        $this->call(AssetStatusSeeder::class);
        $this->call(ModuleDefinitionsSeeder::class);
        $this->call(WorkOrderStatusSeeder::class);
        $this->call(WorkOrderPrioritySeeder::class);
        $this->call(WorkOrderCategorySeeder::class);
        
        // Phase 2: Core Data
        $this->command->info('Phase 2: Core tables...');
        $this->call(CompanySeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(TeamSeeder::class);
        
        // Phase 3: Company-specific Data
        $this->command->info('Phase 3: Company data...');
        $this->call(CompanyModuleSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(LocationSeeder::class);
        $this->call(LocationTemplateSeeder::class);
        $this->call(SupplierSeeder::class);
        $this->call(InventoryCategorySeeder::class);
        $this->call(InventoryLocationSeeder::class);
        
        // Phase 4: Assets & Inventory
        $this->command->info('Phase 4: Assets & Inventory...');
        $this->call(AssetSeeder::class);
        $this->call(AssetTagSeeder::class);
        $this->call(AssetImageSeeder::class);
        $this->call(InventoryPartSeeder::class);
        $this->call(InventoryStockSeeder::class);
        
        // Phase 5: Transactions
        $this->command->info('Phase 5: Transactions...');
        $this->call(AssetActivitySeeder::class);
        $this->call(AssetTransferSeeder::class);
        $this->call(InventoryTransactionSeeder::class);
        $this->call(InventoryAlertSeeder::class);
        $this->call(PurchaseOrderSeeder::class);
        $this->call(PurchaseOrderItemSeeder::class);
        $this->call(PurchaseOrderTemplateSeeder::class);
        
        // Phase 6: Maintenance & Work Orders
        $this->command->info('Phase 6: Maintenance & Work Orders...');
        $this->call(AssetMaintenanceScheduleSeeder::class);
        $this->call(MaintenancePlanSeeder::class);
        $this->call(MaintenancePlanChecklistSeeder::class);
        $this->call(ScheduleMaintenanceSeeder::class);
        $this->call(ScheduleMaintenanceAssignedSeeder::class);
        $this->call(WorkOrderSeeder::class);
        $this->call(WorkOrderAssignmentSeeder::class);
        $this->call(WorkOrderCommentSeeder::class);
        $this->call(WorkOrderTimeLogSeeder::class);
        $this->call(WorkOrderPartSeeder::class);
        
        // Phase 7: Reports & Scopes
        $this->command->info('Phase 7: Reports & Scopes...');
        $this->call(ReportTemplateSeeder::class);
        $this->call(ReportScheduleSeeder::class);
        $this->call(ReportRunSeeder::class);
        $this->call(UserLocationScopeSeeder::class);
        
        // Phase 8: Import Data
        $this->command->info('Phase 8: Import Data...');
        $this->call(ImportSessionSeeder::class);
        $this->call(ImportFileSeeder::class);
        $this->call(ImportMappingSeeder::class);
        
        // Phase 9: AI & Analytics
        $this->command->info('Phase 9: AI & Analytics...');
        $this->call(AIRecognitionHistorySeeder::class);
        $this->call(AITrainingDataSeeder::class);
        $this->call(AIAnalyticsHistorySeeder::class);
        $this->call(PredictiveMaintenanceSeeder::class);
        $this->call(AIRecommendationSeeder::class);
        $this->call(AIAnalyticsRunSeeder::class);
        
        $this->command->info('========================================');
        $this->command->info('Database seeding completed!');
        $this->command->info('========================================');
    }
}
