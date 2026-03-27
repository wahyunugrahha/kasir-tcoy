<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::post('products', [ProductController::class, 'store'])->middleware('role:admin');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('role:admin,cashier');
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class)->middleware('role:admin');
    Route::apiResource('categories', CategoryController::class)->middleware('role:admin');
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');
    Route::apiResource('customers', CustomerController::class);

    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::put('transactions/{transaction}/void', [TransactionController::class, 'void'])->middleware('role:admin');

    Route::get('inventory-movements', [InventoryMovementController::class, 'index']);
    Route::post('inventory-movements', [InventoryMovementController::class, 'store']);

    // Shifts (settlement)
    Route::get('shifts', [ShiftController::class, 'index']);
    Route::post('shifts', [ShiftController::class, 'store']);
    Route::get('shifts/{shift}', [ShiftController::class, 'show']);
    Route::put('shifts/{shift}/close', [ShiftController::class, 'close']);

    // Reports
    Route::get('reports/summary', [ReportController::class, 'summary'])->middleware('role:admin');
    Route::get('reports/sales-by-date', [ReportController::class, 'salesByDate'])->middleware('role:admin');
    Route::get('reports/top-products', [ReportController::class, 'topProducts'])->middleware('role:admin');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin');
});

