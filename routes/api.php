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

    // Location Management routes
    Route::apiResource('locations', LocationController::class);
    Route::post('locations/bulk', [LocationController::class, 'bulkCreate']);
    Route::post('locations/move', [LocationController::class, 'move']);
    Route::get('locations/{location}/qr', [LocationController::class, 'qrCode']);

    // Location helper routes
    Route::get('locations-hierarchy', [LocationController::class, 'hierarchy']);
    Route::get('location-types', [LocationController::class, 'types']);
    Route::get('locations/possible-parents/{locationId?}', [LocationController::class, 'possibleParents']);

    // Custom asset endpoints
    Route::post('assets/bulk-delete', [AssetController::class, 'bulkDelete']);
    Route::post('assets/bulk-archive', [AssetController::class, 'bulkArchive']);
    Route::post('assets/import-bulk', [AssetController::class, 'importBulk']);
    Route::get('assets/import/template', [AssetController::class, 'downloadTemplate']);
    Route::post('assets/{asset}/archive', [AssetController::class, 'archive']);
    Route::get('assets/statistics', [AssetController::class, 'statistics']);
    Route::get('assets-hierarchy', [AssetController::class, 'hierarchy']);
    Route::get('assets/possible-parents/{assetId?}', [AssetController::class, 'possibleParents']);
    Route::post('assets/move', [AssetController::class, 'move']);
    // Asset resource routes
    Route::apiResource('assets', AssetController::class);

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

    // Custom asset endpoints
    Route::post('assets/{asset}/duplicate', [AssetController::class, 'duplicate']);
    Route::post('assets/import/bulk', [AssetController::class, 'bulkImport']);
    Route::post('assets/{asset}/transfer', [AssetController::class, 'transfer']);
    Route::post('assets/{asset}/restore', [AssetController::class, 'restore']);
    Route::post('assets/bulk-restore', [AssetController::class, 'bulkRestore']);

    // Maintenance schedule CRUD
    Route::get('assets/{asset}/maintenance-schedules', [AssetController::class, 'listMaintenanceSchedules']);
    Route::post('assets/{asset}/maintenance-schedules', [AssetController::class, 'addMaintenanceSchedule']);
    Route::put('assets/{asset}/maintenance-schedules/{scheduleId}', [AssetController::class, 'updateMaintenanceSchedule']);
    Route::delete('assets/{asset}/maintenance-schedules/{scheduleId}', [AssetController::class, 'deleteMaintenanceSchedule']);

    // Activity history
    Route::get('assets/{asset}/activity-history', [AssetController::class, 'activityHistory']);
    Route::get('assets/analytics', [AssetController::class, 'analytics']);
    Route::get('assets/export', [AssetController::class, 'export']);

    // Smart Asset Import endpoints
    Route::post('assets/import/upload', [\App\Http\Controllers\Api\AssetImportController::class, 'upload']);
    Route::post('assets/import/analyze', [\App\Http\Controllers\Api\AssetImportController::class, 'analyze']);
    Route::get('assets/import/mappings/{file_id}', [\App\Http\Controllers\Api\AssetImportController::class, 'getMappings']);
    Route::put('assets/import/mappings/{file_id}', [\App\Http\Controllers\Api\AssetImportController::class, 'saveMappings']);
    Route::post('assets/import/conflicts/{file_id}', [\App\Http\Controllers\Api\AssetImportController::class, 'detectConflicts']);
    Route::post('assets/import/resolve-conflicts/{file_id}', [\App\Http\Controllers\Api\AssetImportController::class, 'resolveConflicts']);
    Route::post('assets/import/execute/{file_id}', [\App\Http\Controllers\Api\AssetImportController::class, 'executeImport']);
    Route::get('assets/import/progress/{file_id}', [\App\Http\Controllers\Api\AssetImportController::class, 'importProgress']);
    Route::get('assets/import/template', [\App\Http\Controllers\Api\AssetImportController::class, 'downloadTemplate']);

});

// Routes for verified users only (but don't require full verification middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/resend-authenticated', [AuthController::class, 'resendVerification']);
});
