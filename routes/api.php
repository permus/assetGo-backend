<?php

use App\Http\Controllers\Api\AIAssetAnalyticsController;
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
use App\Http\Controllers\Api\AIImageRecognitionController;
use App\Http\Controllers\Api\PredictiveMaintenanceController;
use App\Http\Controllers\Api\NaturalLanguageController;
use App\Http\Controllers\Api\AIRecommendationsController;
use App\Http\Controllers\Api\AIAnalyticsController;
use App\Http\Controllers\Api\AssetReportController;
use App\Http\Controllers\Api\MaintenanceReportController;
use App\Http\Controllers\Api\ReportExportController;

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
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:10,1'); // 10 registration attempts per minute
Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['throttle:5,1', \App\Http\Middleware\ThrottleLoginAttempts::class]); // 5 login attempts per minute + account lockout
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1'); // 3 password reset requests per minute
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:3,1'); // 3 password reset attempts per minute
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
Route::post('/email/resend', [AuthController::class, 'resendVerification'])
    ->middleware('throttle:5,1'); // 5 resend attempts per minute

Route::middleware(['auth:sanctum'])->group(function () {
    // Dashboard
    Route::get('dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);
    // Auth routes
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'uploadAvatar']);
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

    // AI Image Recognition routes
    Route::prefix('ai/image-recognition')->group(function () {
        Route::post('analyze', [AIImageRecognitionController::class, 'analyze'])
            ->middleware('throttle:10,1'); // 10 requests per minute
        Route::post('feedback', [AIImageRecognitionController::class, 'feedback'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('history', [AIImageRecognitionController::class, 'history'])
            ->middleware('throttle:60,1'); // 60 requests per minute
    });

    // AI Asset Analytics routes
    Route::prefix('ai/asset-analytics')->group(function () {
        Route::post('analyze', [AIAssetAnalyticsController::class, 'analyze'])
            ->middleware('throttle:5,1'); // 5 requests per minute (more complex analysis)
    });

// AI Predictive Maintenance routes
Route::prefix('ai/predictive-maintenance')->group(function () {
    Route::get('/', [PredictiveMaintenanceController::class, 'index'])
        ->middleware('throttle:60,1'); // 60 requests per minute
    Route::post('generate', [PredictiveMaintenanceController::class, 'generate'])
        ->middleware('throttle:2,1'); // 2 requests per minute (AI intensive)
    Route::get('export', [PredictiveMaintenanceController::class, 'export'])
        ->middleware('throttle:10,1'); // 10 requests per minute
    Route::get('summary', [PredictiveMaintenanceController::class, 'summary'])
        ->middleware('throttle:60,1'); // 60 requests per minute
    Route::delete('clear', [PredictiveMaintenanceController::class, 'clear'])
        ->middleware('throttle:5,1'); // 5 requests per minute
});

// AI Natural Language routes
Route::prefix('ai/natural-language')->group(function () {
    Route::get('context', [NaturalLanguageController::class, 'getContext'])
        ->middleware('throttle:60,1'); // 60 requests per minute
    Route::post('chat', [NaturalLanguageController::class, 'chat'])
        ->middleware('throttle:10,1'); // 10 requests per minute (AI intensive)
    Route::get('check-api-key', [NaturalLanguageController::class, 'checkApiKey'])
        ->middleware('throttle:30,1'); // 30 requests per minute
});



    // Location Management routes
    // Place static routes BEFORE resource to avoid model-binding catching 'tree' as {location}
    Route::get('locations/tree', [TeamController::class, 'locationTree']);
    Route::get('locations/export-qr', [LocationController::class, 'exportQRCodes'])
        ->middleware('throttle:10,1'); // 10 QR exports per minute
    Route::post('locations/bulk', [LocationController::class, 'bulkCreate'])
        ->middleware('throttle:10,1'); // 10 bulk creates per minute
    Route::post('locations/move', [LocationController::class, 'move'])
        ->middleware('throttle:30,1'); // 30 moves per minute
    Route::get('locations/{location}/qr', [LocationController::class, 'qrCode']);
    Route::get('locations/{location}/assets', [LocationController::class, 'getLocationAssets'])
        ->middleware('throttle:60,1'); // 60 requests per minute
    Route::get('locations/{location}/assignable-assets', [LocationController::class, 'getAssignableAssets'])
        ->middleware('throttle:60,1'); // 60 requests per minute
    Route::post('locations/{location}/assign-assets', [LocationController::class, 'assignAssets'])
        ->middleware('throttle:30,1'); // 30 requests per minute
    Route::apiResource('locations', LocationController::class);
    Route::get('locations-hierarchy', [LocationController::class, 'hierarchy'])
        ->middleware('throttle:60,1'); // 60 hierarchy requests per minute
    Route::get('location-types', [LocationController::class, 'types']);
    Route::get('locations/possible-parents/{locationId?}', [LocationController::class, 'possibleParents']);

    // Asset resource routes (guarded by module enablement)
    Route::middleware('module:assets')->group(function () {
    Route::post('assets/bulk-delete', [AssetController::class, 'bulkDelete'])
        ->middleware('throttle:20,1'); // 20 bulk deletes per minute
    Route::post('assets/bulk-archive', [AssetController::class, 'bulkArchive'])
        ->middleware('throttle:20,1'); // 20 bulk archives per minute
    Route::post('assets/import-bulk-excel', [AssetController::class, 'bulkImportAssetsFromExcel'])
        ->middleware('throttle:5,1'); // 5 imports per minute
    Route::get('assets/import-progress/{jobId}', [AssetController::class, 'importProgress']);
    Route::get('assets/import/template', [AssetController::class, 'downloadTemplate']);
    Route::get('assets/statistics', [AssetController::class, 'statistics'])
        ->middleware('throttle:30,1'); // 30 stats requests per minute
    Route::get('assets/export-excel', [AssetController::class, 'exportExcel'])
        ->middleware('throttle:10,1'); // 10 exports per minute
    Route::post('assets/{asset}/archive', [AssetController::class, 'archive']);
    Route::get('assets-hierarchy', [AssetController::class, 'hierarchy']);
    Route::get('assets/possible-parents/{assetId?}', [AssetController::class, 'possibleParents']);
    Route::post('assets/move', [AssetController::class, 'move']);
    Route::apiResource('assets', AssetController::class);
    Route::post('assets/{asset}/duplicate', [AssetController::class, 'duplicate']);
    Route::post('assets/{asset}/transfer', [AssetController::class, 'transfer']);
    Route::post('assets/{asset}/restore', [AssetController::class, 'restore']);
    Route::post('assets/bulk-restore', [AssetController::class, 'bulkRestore'])
        ->middleware('throttle:20,1'); // 20 bulk restores per minute
    Route::get('assets/{asset}/qr-code', [AssetController::class, 'qrCode']);
    Route::get('assets/{asset}/barcode', [AssetController::class, 'barcode']);
    Route::get('assets/barcode-types', [AssetController::class, 'barcodeTypes']);
    Route::get('assets/{asset}/related', [AssetController::class, 'relatedAssets']);
    Route::get('assets/{asset}/chart-data', [AssetController::class, 'chartData']);
    Route::get('assets/{asset}/health-performance-chart', [AssetController::class, 'healthPerformanceChart']);
    Route::get('assets/{asset}/activity-history', [AssetController::class, 'activityHistory']);
    Route::get('assets/activities', [AssetController::class, 'allActivities']);
    Route::get('assets/analytics', [AssetController::class, 'analytics'])
        ->middleware('throttle:30,1'); // 30 analytics requests per minute
    });

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
    // Place static routes BEFORE resource to avoid model-binding catching them as {team}
    Route::get('teams/statistics', [TeamController::class, 'statistics'])
        ->middleware('throttle:30,1');
    Route::get('teams/analytics', [TeamController::class, 'analytics'])
        ->middleware('throttle:30,1');
    Route::get('teams/available-roles', [TeamController::class, 'getAvailableRoles']);
    Route::post('teams/{id}/resend-invitation', [TeamController::class, 'resendInvitation']);
    Route::apiResource('teams', TeamController::class);

    // Feature flags for current user
    Route::get('me/features', [FeatureFlagsController::class, 'me']);

    // Work Order routes (guarded)
    Route::middleware('module:work_orders')->group(function () {
        Route::get('work-orders/count', [WorkOrderController::class, 'count']);
        Route::get('work-orders/analytics', [WorkOrderController::class, 'analytics'])
            ->middleware('throttle:30,1');
        Route::get('work-orders/statistics', [WorkOrderController::class, 'statistics'])
            ->middleware('throttle:30,1');
        Route::get('work-orders/filters', [WorkOrderController::class, 'filters'])
            ->middleware('throttle:60,1');
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
    });

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

    // Inventory module routes (guarded)
    Route::middleware('module:inventory')->group(function () {
        // Parts Catalog
        // Important: put specific routes BEFORE resource to avoid capturing 'overview' as {part}
        Route::get('inventory/parts/overview', [InventoryPartController::class, 'overview'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('inventory/parts/{part}/archive', [InventoryPartController::class, 'archive'])
            ->middleware('throttle:60,1');
        Route::post('inventory/parts/{part}/restore', [InventoryPartController::class, 'restore'])
            ->middleware('throttle:60,1');
        Route::apiResource('inventory/parts', InventoryPartController::class);

        // Stock Levels & Adjustments
        Route::get('inventory/stocks', [InventoryStockController::class, 'index']);
        Route::post('inventory/stocks/adjust', [InventoryStockController::class, 'adjust'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('inventory/stocks/transfer', [InventoryStockController::class, 'transfer'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('inventory/stocks/reserve', [InventoryStockController::class, 'reserve']);
        Route::post('inventory/stocks/release', [InventoryStockController::class, 'release']);
        Route::post('inventory/stocks/count', [InventoryStockController::class, 'count']);

        // Transactions
        Route::get('inventory/transactions', [InventoryTransactionController::class, 'index']);

        // Suppliers
        Route::apiResource('inventory/suppliers', InventorySupplierController::class)->only(['index', 'store', 'update']);

        // Purchase Orders
        Route::get('inventory/purchase-orders', [InventoryPOController::class, 'index']);
        Route::get('inventory/purchase-orders/overview', [InventoryPOController::class, 'overview'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('inventory/purchase-orders', [InventoryPOController::class, 'store'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::put('inventory/purchase-orders/{purchaseOrder}', [InventoryPOController::class, 'update']);
        Route::post('inventory/purchase-orders/{purchaseOrder}/receive', [InventoryPOController::class, 'receive']);
        Route::post('inventory/purchase-orders/approve', [InventoryPOController::class, 'approve']);

        // Analytics
        Route::get('inventory/analytics/dashboard', [InventoryAnalyticsController::class, 'dashboard'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('inventory/dashboard/overview', [InventoryDashboardController::class, 'overview'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('inventory/analytics/abc-analysis', [InventoryAnalyticsController::class, 'abcAnalysis'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('inventory/analytics/abc-analysis/export', [InventoryAnalyticsController::class, 'abcAnalysisExport'])
            ->middleware('throttle:10,1'); // 10 exports per minute
        Route::get('inventory/analytics/kpis', [InventoryAnalyticsController::class, 'kpis'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('inventory/analytics/turnover', [InventoryAnalyticsController::class, 'turnover'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('inventory/analytics/turnover-by-category', [InventoryAnalyticsController::class, 'turnoverByCategory'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('inventory/analytics/monthly-turnover-trend', [InventoryAnalyticsController::class, 'monthlyTurnoverTrend'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('inventory/analytics/stock-aging', [InventoryAnalyticsController::class, 'stockAging'])
            ->middleware('throttle:30,1'); // 30 requests per minute
    });

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
        Route::get('plans', [\App\Http\Controllers\Api\Maintenance\MaintenancePlansController::class, 'index'])
            ->middleware('throttle:60,1');
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
        Route::get('schedules', [\App\Http\Controllers\Api\Maintenance\ScheduleMaintenanceController::class, 'index'])
            ->middleware('throttle:60,1');
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

    

    // AI Recommendations routes
    Route::prefix('ai/recommendations')->group(function () {
        Route::get('/', [AIRecommendationsController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('generate', [AIRecommendationsController::class, 'generate'])
            ->middleware('throttle:2,1'); // 2 requests per minute (AI intensive)
        Route::post('{id}/toggle', [AIRecommendationsController::class, 'toggleImplementation'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::get('export', [AIRecommendationsController::class, 'export'])
            ->middleware('throttle:10,1'); // 10 requests per minute
        Route::get('summary', [AIRecommendationsController::class, 'summary'])
            ->middleware('throttle:60,1'); // 60 requests per minute
    });

    // AI Analytics routes
    Route::prefix('ai/analytics')->group(function () {
        Route::get('/', [AIAnalyticsController::class, 'index'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::post('generate', [AIAnalyticsController::class, 'generate'])
            ->middleware('throttle:2,1'); // 2 requests per minute (AI intensive)
        Route::get('export', [AIAnalyticsController::class, 'export'])
            ->middleware('throttle:10,1'); // 10 requests per minute
        Route::get('schedule', [AIAnalyticsController::class, 'getSchedule'])
            ->middleware('throttle:30,1'); // 30 requests per minute
        Route::post('schedule', [AIAnalyticsController::class, 'updateSchedule'])
            ->middleware('throttle:10,1'); // 10 requests per minute
    });

    // Reports routes
    Route::prefix('reports')->group(function () {
        // Asset Reports
        Route::prefix('assets')->group(function () {
            Route::get('summary', [AssetReportController::class, 'summary'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('utilization', [AssetReportController::class, 'utilization'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('depreciation', [AssetReportController::class, 'depreciation'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('warranty', [AssetReportController::class, 'warranty'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('compliance', [AssetReportController::class, 'compliance'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('available', [AssetReportController::class, 'available'])
                ->middleware('throttle:60,1'); // 60 requests per minute
        });

        // Maintenance Reports
        Route::prefix('maintenance')->group(function () {
            Route::get('summary', [MaintenanceReportController::class, 'summary'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('compliance', [MaintenanceReportController::class, 'compliance'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('costs', [MaintenanceReportController::class, 'costs'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('downtime', [MaintenanceReportController::class, 'downtime'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('failure-analysis', [MaintenanceReportController::class, 'failureAnalysis'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('technician-performance', [MaintenanceReportController::class, 'technicianPerformance'])
                ->middleware('throttle:60,1'); // 60 requests per minute
            Route::get('available', [MaintenanceReportController::class, 'available'])
                ->middleware('throttle:60,1'); // 60 requests per minute
        });

        // Export routes
        Route::post('export', [ReportExportController::class, 'export'])
            ->middleware('throttle:10,1'); // 10 requests per minute
        Route::get('runs/{id}', [ReportExportController::class, 'show'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::get('runs/{id}/download', [ReportExportController::class, 'download'])
            ->name('reports.download')
            ->withoutMiddleware(['auth:sanctum']);
        Route::get('history', [ReportExportController::class, 'history'])
            ->middleware('throttle:60,1'); // 60 requests per minute
        Route::delete('runs/{id}/cancel', [ReportExportController::class, 'cancel'])
            ->middleware('throttle:30,1'); // 30 requests per minute
    });
});

// Routes for verified users only (but don't require full verification middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/resend-authenticated', [AuthController::class, 'resendVerification']);
});
