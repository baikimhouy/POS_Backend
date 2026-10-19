<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// Laravel adds the /api prefix, so these are served under /api/v1/...
Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::patch('user/profile', [ProfileController::class, 'update']);
        Route::put('user/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1');

        // Staff (admin + cashier)
        Route::get('settings', [SettingController::class, 'show']);
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('variants', [ProductController::class, 'variants']);
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::get('sales', [SaleController::class, 'index']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);

        // Admin only. Authorization lives here on the server; hiding buttons in React is not security.
        Route::middleware('role:admin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);

            Route::get('dashboard', DashboardController::class);
            Route::patch('settings', [SettingController::class, 'update']);
            Route::post('categories', [CategoryController::class, 'store']);
            Route::post('products', [ProductController::class, 'store']);
            Route::patch('products/{product}', [ProductController::class, 'update']);
            Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);
            Route::delete('products/{product}/image', [ProductController::class, 'deleteImage']);
            Route::post('products/{product}/variants', [ProductController::class, 'storeVariant']);
            Route::patch('variants/{variant}', [ProductController::class, 'updateVariant']);
            Route::post('variants/{variant}/adjust', [InventoryController::class, 'adjust']);
            Route::get('variants/{variant}/movements', [InventoryController::class, 'movements']);
            Route::patch('customers/{customer}', [CustomerController::class, 'update']);
            Route::post('sales/{sale}/void', [SaleController::class, 'void']);
        });
    });
});
