<?php

use App\Http\Controllers\Api\LocationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AssetCategoryController;
use App\Http\Controllers\Api\AssetTypeController;
use App\Http\Controllers\Api\AssetStatusController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\FeatureFlagsController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\Api\MetaWorkOrderController;
use App\Http\Controllers\Api\Inventory\PartController as InventoryPartController;
use App\Http\Controllers\Api\Inventory\StockController as InventoryStockController;
use App\Http\Controllers\Api\Inventory\TransactionController as InventoryTransactionController;
use App\Http\Controllers\Api\Inventory\PurchaseOrderController as InventoryPOController;
use App\Http\Controllers\Api\Inventory\SupplierController as InventorySupplierController;
use App\Http\Controllers\Api\Inventory\AnalyticsController as InventoryAnalyticsController;
use App\Http\Controllers\Api\Inventory\DashboardController as InventoryDashboardController;
use App\Http\Controllers\Api\Inventory\CategoryController as InventoryCategoryController;
use App\Http\Controllers\Api\Inventory\PurchaseOrderTemplateController as InventoryPurchaseOrderTemplateController;
use App\Http\Controllers\Api\Inventory\AlertController as InventoryAlertController;
use App\Http\Controllers\Api\CompanySettingsController;
use App\Http\Controllers\Api\ModuleSettingsController;
use App\Http\Controllers\Api\PreferencesController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public asset routes
Route::get('/assets/{id}/public', [AssetController::class, 'publicShow']);
Route::get('/assets/public/statistics', [AssetController::class, 'publicStatistics']);


// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/resend', [AuthController::class, 'resendVerification']);

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Auth routes
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // Company routes
    Route::get('/company', [CompanyController::class, 'show']);
    Route::put('/company', [CompanyController::class, 'update']);
    Route::get('/company/users', [CompanyController::class, 'users']);

    // Settings - Company
    Route::put('/settings/currency', [CompanySettingsController::class, 'updateCurrency']);
    Route::post('/settings/company/logo', [CompanySettingsController::class, 'uploadLogo']);

    // Settings - Modules
    Route::get('/settings/modules', [ModuleSettingsController::class, 'index']);
    Route::post('/settings/modules/{module}/enable', [ModuleSettingsController::class, 'enable']);
    Route::post('/settings/modules/{module}/disable', [ModuleSettingsController::class, 'disable']);

    // Settings - Preferences (optional)
    Route::get('/settings/preferences', [PreferencesController::class, 'show']);
    Route::put('/settings/preferences', [PreferencesController::class, 'update']);

    // Location Management routes
    // Place static routes BEFORE resource to avoid model-binding catching 'tree' as {location}
    Route::get('locations/tree', [TeamController::class, 'locationTree']);
    Route::apiResource('locations', LocationController::class);
    Route::post('locations/bulk', [LocationController::class, 'bulkCreate']);
    Route::post('locations/move', [LocationController::class, 'move']);
    Route::get('locations/{location}/qr', [LocationController::class, 'qrCode']);
    Route::get('locations-hierarchy', [LocationController::class, 'hierarchy']);
    Route::get('location-types', [LocationController::class, 'types']);
    Route::get('locations/possible-parents/{locationId?}', [LocationController::class, 'possibleParents']);

    // Asset resource routes
    Route::post('assets/bulk-delete', [AssetController::class, 'bulkDelete']);
    Route::post('assets/bulk-archive', [AssetController::class, 'bulkArchive']);
    Route::post('assets/import-bulk-excel', [AssetController::class, 'bulkImportAssetsFromExcel']);
    Route::get('assets/import-progress/{jobId}', [AssetController::class, 'importProgress']);
    Route::get('assets/import/template', [AssetController::class, 'downloadTemplate']);
    Route::get('assets/statistics', [AssetController::class, 'statistics']);
    Route::get('assets/export-excel', [AssetController::class, 'exportExcel']);
    Route::post('assets/{asset}/archive', [AssetController::class, 'archive']);
    Route::get('assets-hierarchy', [AssetController::class, 'hierarchy']);
    Route::get('assets/possible-parents/{assetId?}', [AssetController::class, 'possibleParents']);
    Route::post('assets/move', [AssetController::class, 'move']);
    Route::apiResource('assets', AssetController::class);

    Route::post('assets/{asset}/duplicate', [AssetController::class, 'duplicate']);
    Route::post('assets/{asset}/transfer', [AssetController::class, 'transfer']);
    Route::post('assets/{asset}/restore', [AssetController::class, 'restore']);
    Route::post('assets/bulk-restore', [AssetController::class, 'bulkRestore']);
    Route::get('assets/{asset}/qr-code', [AssetController::class, 'qrCode']);
    Route::get('assets/{asset}/barcode', [AssetController::class, 'barcode']);
    Route::get('assets/barcode-types', [AssetController::class, 'barcodeTypes']);
    Route::get('assets/{asset}/related', [AssetController::class, 'relatedAssets']);
    Route::get('assets/{asset}/chart-data', [AssetController::class, 'chartData']);
    Route::get('assets/{asset}/health-performance-chart', [AssetController::class, 'healthPerformanceChart']);
    Route::get('assets/{asset}/activity-history', [AssetController::class, 'activityHistory']);
    Route::get('assets/activities', [AssetController::class, 'allActivities']);
    Route::get('assets/analytics', [AssetController::class, 'analytics']);

    // Maintenance schedule CRUD
    Route::get('assets/{asset}/maintenance-schedules', [AssetController::class, 'listMaintenanceSchedules']);
    Route::post('assets/{asset}/maintenance-schedules', [AssetController::class, 'addMaintenanceSchedule']);
    Route::put('assets/{asset}/maintenance-schedules/{scheduleId}', [AssetController::class, 'updateMaintenanceSchedule']);
    Route::delete('assets/{asset}/maintenance-schedules/{scheduleId}', [AssetController::class, 'deleteMaintenanceSchedule']);


    // Asset category routes
    Route::apiResource('asset-categories', AssetCategoryController::class);
    Route::get('asset-categories-list', [AssetCategoryController::class, 'list']);

    // Asset type routes
    Route::apiResource('asset-types', AssetTypeController::class);
    Route::get('asset-types-list', [AssetTypeController::class, 'list']);

    // Asset status routes
    Route::apiResource('asset-statuses', AssetStatusController::class);
    Route::get('asset-statuses-list', [AssetStatusController::class, 'list']);

    // Department routes
    Route::apiResource('departments', DepartmentController::class);
    Route::get('departments-list', [DepartmentController::class, 'list']);

    // Role and Permission routes
    Route::get('roles/available-permissions', [RoleController::class, 'getAvailablePermissions']);
    Route::post('roles/assign-to-user', [RoleController::class, 'assignToUser']);
    Route::post('roles/remove-from-user', [RoleController::class, 'removeFromUser']);
    Route::apiResource('roles', RoleController::class);

    // Team routes (team members are users with user_type = 'team')
    Route::apiResource('teams', TeamController::class);
    Route::post('teams/{id}/resend-invitation', [TeamController::class, 'resendInvitation']);
    Route::get('teams/statistics', [TeamController::class, 'statistics']);
    Route::get('teams/available-roles', [TeamController::class, 'getAvailableRoles']);
    // (moved above before apiResource to avoid conflicts)

    // Feature flags for current user
    Route::get('me/features', [FeatureFlagsController::class, 'me']);

    // Work Order routes
    Route::get('work-orders/count', [WorkOrderController::class, 'count']);
    Route::get('work-orders/analytics', [WorkOrderController::class, 'analytics']);
    Route::get('work-orders/statistics', [WorkOrderController::class, 'statistics']);
    Route::get('work-orders/filters', [WorkOrderController::class, 'filters']);
    Route::post('work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus']);
    Route::get('work-orders/{workOrder}/history', [WorkOrderController::class, 'history']);
    // Work Order assignments
    Route::get('work-orders/{workOrder}/assignments', [\App\Http\Controllers\Api\WorkOrderAssignmentController::class, 'index']);
Route::post('work-orders/{workOrder}/assignments', [\App\Http\Controllers\Api\WorkOrderAssignmentController::class, 'store']);
Route::post('work-orders/{workOrder}/assign', [\App\Http\Controllers\Api\WorkOrderAssignmentController::class, 'assign']);
Route::put('work-orders/{workOrder}/assignments/{assignment}', [\App\Http\Controllers\Api\WorkOrderAssignmentController::class, 'update']);
Route::patch('work-orders/{workOrder}/assignments/{assignment}', [\App\Http\Controllers\Api\WorkOrderAssignmentController::class, 'update']);
Route::delete('work-orders/{workOrder}/assignments/{assignment}', [\App\Http\Controllers\Api\WorkOrderAssignmentController::class, 'destroy']);
    // Work Order parts
    Route::get('work-orders/{workOrder}/parts', [\App\Http\Controllers\Api\WorkOrderPartController::class, 'index']);
    Route::post('work-orders/{workOrder}/parts', [\App\Http\Controllers\Api\WorkOrderPartController::class, 'store']);
    Route::put('work-orders/{workOrder}/parts/{part}', [\App\Http\Controllers\Api\WorkOrderPartController::class, 'update']);
    Route::delete('work-orders/{workOrder}/parts/{part}', [\App\Http\Controllers\Api\WorkOrderPartController::class, 'destroy']);
    Route::apiResource('work-orders', WorkOrderController::class);

    // Work Order comments
    Route::get('work-orders/{workOrder}/comments', [\App\Http\Controllers\Api\WorkOrderCommentController::class, 'index']);
    Route::post('work-orders/{workOrder}/comments', [\App\Http\Controllers\Api\WorkOrderCommentController::class, 'store']);
    Route::delete('work-orders/{workOrder}/comments/{comment}', [\App\Http\Controllers\Api\WorkOrderCommentController::class, 'destroy']);

    // Work Order time tracking
    Route::get('work-orders/{workOrder}/time-logs', [\App\Http\Controllers\Api\WorkOrderTimeLogController::class, 'index']);
    Route::post('work-orders/{workOrder}/time-logs/start', [\App\Http\Controllers\Api\WorkOrderTimeLogController::class, 'start']);
    Route::post('work-orders/{workOrder}/time-logs/stop', [\App\Http\Controllers\Api\WorkOrderTimeLogController::class, 'stop']);

    // Work Order Meta routes
    Route::prefix('meta/work-orders')->group(function () {
        Route::get('status', [MetaWorkOrderController::class, 'statusIndex']);
        Route::get('priorities', [MetaWorkOrderController::class, 'priorityIndex']);
        Route::get('categories', [MetaWorkOrderController::class, 'categoryIndex']);

        Route::post('status', [MetaWorkOrderController::class, 'statusStore']);
        Route::put('status/{id}', [MetaWorkOrderController::class, 'statusUpdate']);
        Route::delete('status/{id}', [MetaWorkOrderController::class, 'statusDestroy']);

        Route::post('priorities', [MetaWorkOrderController::class, 'priorityStore']);
        Route::put('priorities/{id}', [MetaWorkOrderController::class, 'priorityUpdate']);
        Route::delete('priorities/{id}', [MetaWorkOrderController::class, 'priorityDestroy']);

        Route::post('categories', [MetaWorkOrderController::class, 'categoryStore']);
        Route::put('categories/{id}', [MetaWorkOrderController::class, 'categoryUpdate']);
        Route::delete('categories/{id}', [MetaWorkOrderController::class, 'categoryDestroy']);
    });

    // Inventory module routes
    // Parts Catalog
    // Important: put specific routes BEFORE resource to avoid capturing 'overview' as {part}
    Route::get('inventory/parts/overview', [InventoryPartController::class, 'overview']);
    Route::apiResource('inventory/parts', InventoryPartController::class);

    // Stock Levels & Adjustments
    Route::get('inventory/stocks', [InventoryStockController::class, 'index']);
    Route::post('inventory/stocks/adjust', [InventoryStockController::class, 'adjust']);
    Route::post('inventory/stocks/transfer', [InventoryStockController::class, 'transfer']);
    Route::post('inventory/stocks/reserve', [InventoryStockController::class, 'reserve']);
    Route::post('inventory/stocks/release', [InventoryStockController::class, 'release']);
    Route::post('inventory/stocks/count', [InventoryStockController::class, 'count']);

    // Transactions
    Route::get('inventory/transactions', [InventoryTransactionController::class, 'index']);

    // Suppliers
    Route::apiResource('inventory/suppliers', InventorySupplierController::class)->only(['index', 'store', 'update']);

    // Purchase Orders
    Route::get('inventory/purchase-orders', [InventoryPOController::class, 'index']);
    Route::get('inventory/purchase-orders/overview', [InventoryPOController::class, 'overview']);
    Route::post('inventory/purchase-orders', [InventoryPOController::class, 'store']);
    Route::put('inventory/purchase-orders/{purchaseOrder}', [InventoryPOController::class, 'update']);
    Route::post('inventory/purchase-orders/{purchaseOrder}/receive', [InventoryPOController::class, 'receive']);
    Route::post('inventory/purchase-orders/approve', [InventoryPOController::class, 'approve']);

    // Analytics
    Route::get('inventory/analytics/dashboard', [InventoryAnalyticsController::class, 'dashboard']);
    Route::get('inventory/dashboard/overview', [InventoryDashboardController::class, 'overview']);
    Route::get('inventory/analytics/abc-analysis', [InventoryAnalyticsController::class, 'abcAnalysis']);
    Route::get('inventory/analytics/abc-analysis/export', [InventoryAnalyticsController::class, 'abcAnalysisExport']);
    Route::get('inventory/analytics/kpis', [InventoryAnalyticsController::class, 'kpis']);
    Route::get('inventory/analytics/turnover', [InventoryAnalyticsController::class, 'turnover']);
    Route::get('inventory/analytics/turnover-by-category', [InventoryAnalyticsController::class, 'turnoverByCategory']);
    Route::get('inventory/analytics/monthly-turnover-trend', [InventoryAnalyticsController::class, 'monthlyTurnoverTrend']);
    Route::get('inventory/analytics/stock-aging', [InventoryAnalyticsController::class, 'stockAging']);

// New: categories, templates, alerts
    Route::get('inventory/categories', [InventoryCategoryController::class, 'index']);
    Route::post('inventory/categories', [InventoryCategoryController::class, 'store']);
    Route::put('inventory/categories/{category}', [InventoryCategoryController::class, 'update']);
    Route::delete('inventory/categories/{category}', [InventoryCategoryController::class, 'destroy']);

    Route::get('inventory/purchase-order-templates', [InventoryPurchaseOrderTemplateController::class, 'index']);
    Route::post('inventory/purchase-order-templates', [InventoryPurchaseOrderTemplateController::class, 'store']);
    Route::put('inventory/purchase-order-templates/{purchaseOrderTemplate}', [InventoryPurchaseOrderTemplateController::class, 'update']);
    Route::delete('inventory/purchase-order-templates/{purchaseOrderTemplate}', [InventoryPurchaseOrderTemplateController::class, 'destroy']);

    Route::get('inventory/alerts', [InventoryAlertController::class, 'index']);
    Route::post('inventory/alerts', [InventoryAlertController::class, 'store']);
    Route::post('inventory/alerts/{alert}/resolve', [InventoryAlertController::class, 'resolve']);

    // Maintenance module routes
    Route::prefix('maintenance')->group(function () {
        // Plans
        Route::get('plans', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'index']);
        Route::post('plans', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'store']);
        Route::get('plans/{maintenancePlan}', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'show']);
        Route::put('plans/{maintenancePlan}', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'update']);
        Route::delete('plans/{maintenancePlan}', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'destroy']);
        Route::patch('plans/{maintenancePlan}/toggle-active', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'toggleActive']);

        // Checklists
        Route::get('plans-checklists', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansChecklistsController::class, 'index']);
        Route::post('plans-checklists', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansChecklistsController::class, 'store']);
        Route::get('plans-checklists/{maintenancePlanChecklist}', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansChecklistsController::class, 'show']);
        Route::put('plans-checklists/{maintenancePlanChecklist}', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansChecklistsController::class, 'update']);
        Route::delete('plans-checklists/{maintenancePlanChecklist}', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansChecklistsController::class, 'destroy']);

        // Schedules
        Route::get('schedules', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceController::class, 'index']);
        Route::post('schedules', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceController::class, 'store']);
        Route::get('schedules/{scheduleMaintenance}', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceController::class, 'show']);
        Route::put('schedules/{scheduleMaintenance}', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceController::class, 'update']);
        Route::delete('schedules/{scheduleMaintenance}', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceController::class, 'destroy']);

        // Assignments
        Route::get('schedule-assignments', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceAssignedController::class, 'index']);
        Route::post('schedule-assignments', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceAssignedController::class, 'store']);
        Route::get('schedule-assignments/{scheduleMaintenanceAssigned}', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceAssignedController::class, 'show']);
        Route::put('schedule-assignments/{scheduleMaintenanceAssigned}', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceAssignedController::class, 'update']);
        Route::delete('schedule-assignments/{scheduleMaintenanceAssigned}', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceAssignedController::class, 'destroy']);
    });
});

// Routes for verified users only (but don't require full verification middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/resend-authenticated', [AuthController::class, 'resendVerification']);
});
