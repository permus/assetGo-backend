<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;

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

    // Location routes
    Route::prefix('locations')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\LocationController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\LocationController::class, 'store']);
        Route::get('/hierarchy', [App\Http\Controllers\Api\LocationController::class, 'hierarchy']);
        Route::get('/types', [App\Http\Controllers\Api\LocationController::class, 'types']);
        Route::get('/possible-parents/{location?}', [App\Http\Controllers\Api\LocationController::class, 'possibleParents']);
        Route::get('/{location}', [App\Http\Controllers\Api\LocationController::class, 'show']);
        Route::put('/{location}', [App\Http\Controllers\Api\LocationController::class, 'update']);
        Route::delete('/{location}', [App\Http\Controllers\Api\LocationController::class, 'destroy']);
        Route::get('/{location}/qr-code/download', [App\Http\Controllers\Api\LocationController::class, 'downloadQRCode']);
    });
});

// Routes for verified users only (but don't require full verification middleware)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/resend-authenticated', [AuthController::class, 'resendVerification']);
});