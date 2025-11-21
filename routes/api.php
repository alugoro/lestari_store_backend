<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductTypeController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (tidak perlu authentication)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Test route (untuk testing CORS sebelumnya)
Route::get('/test', function () {
    return response()->json([
        'message' => 'API Works!',
        'status' => 'success',
        'timestamp' => now()
    ]);
});

// Protected routes (perlu authentication)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Routes untuk semua role (admin, owner, kasir)
    Route::prefix('dashboard')->group(function () {
        // Today's realtime report
        Route::get('/today', [DailyReportController::class, 'today']);
    });

    // Routes khusus admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/roles', [UserController::class, 'roles']); // Get available roles
            Route::get('/statistics', [UserController::class, 'statistics']); // User stats
            Route::get('/{id}', [UserController::class, 'show']);
            Route::post('/', [UserController::class, 'store']); // Create user
            Route::put('/{id}', [UserController::class, 'update']); // Update user
            Route::delete('/{id}', [UserController::class, 'destroy']); // Delete user
            Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus']); // Activate/Deactivate
        });
    });

    // Routes untuk admin & owner
    Route::middleware('role:admin,owner')->prefix('management')->group(function () {
        // Purchase Management (Restock)
        Route::prefix('purchases')->group(function () {
            Route::get('/', [PurchaseController::class, 'index']);
            Route::get('/{id}', [PurchaseController::class, 'show']);
            Route::post('/', [PurchaseController::class, 'store']); // Create purchase/restock
            Route::delete('/{id}', [PurchaseController::class, 'destroy']);
        });

        // Stock Movements
        Route::prefix('stock-movements')->group(function () {
            Route::get('/', [StockMovementController::class, 'index']);
            Route::post('/adjustment', [StockMovementController::class, 'adjustment']); // Stock adjustment
            Route::get('/product/{productId}', [StockMovementController::class, 'productHistory']);
            Route::get('/low-stock', [StockMovementController::class, 'lowStock']);
        });

        // Daily Reports & Financial
        Route::prefix('reports')->group(function () {
            Route::get('/', [DailyReportController::class, 'index']); // List all reports
            Route::get('/date/{date}', [DailyReportController::class, 'show']); // Get specific date
            Route::post('/generate', [DailyReportController::class, 'generate']); // Manual generate
            Route::get('/monthly-summary', [DailyReportController::class, 'monthlySummary']);
            Route::get('/range-summary', [DailyReportController::class, 'rangeSummary']);
        });
    });

    // Routes untuk semua authenticated user
    
    // Product Types (read only untuk semua user)
    Route::prefix('product-types')->group(function () {
        Route::get('/', [ProductTypeController::class, 'index']);
        Route::get('/{id}', [ProductTypeController::class, 'show']);
    });

    // Products CRUD
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
        
        // Create, Update, Delete hanya untuk admin, owner, kasir
        Route::post('/', [ProductController::class, 'store']);
        Route::post('/{id}', [ProductController::class, 'update']); // POST untuk support image upload
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
    });

    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/today-summary', [TransactionController::class, 'todaySummary']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::post('/', [TransactionController::class, 'store']); // Checkout
    });
});