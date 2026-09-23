<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\HeldOrderController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::post('products', [ProductController::class, 'store'])->middleware('role:admin');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('role:admin,cashier');
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('managers', [UserController::class, 'managers']);
    Route::post('managers/verify-pin', [UserController::class, 'verifyManagerPin'])->middleware('throttle:6,1');
    Route::apiResource('users', UserController::class)->middleware('role:admin');
    Route::apiResource('categories', CategoryController::class)->middleware('role:admin');
    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');
    Route::apiResource('customers', CustomerController::class);

    Route::post('promo-codes/validate', [PromoCodeController::class, 'validateCode'])->middleware('role:admin,cashier');
    Route::apiResource('promo-codes', PromoCodeController::class)->middleware('role:admin');

    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store'])->middleware('role:admin,cashier');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('transactions/{transaction}/pay', [TransactionController::class, 'pay'])->middleware('role:admin,cashier');
    Route::put('transactions/{transaction}/void', [TransactionController::class, 'void'])->middleware('role:admin,cashier');
    Route::post('transactions/{transaction}/refund', [TransactionController::class, 'refund'])->middleware('role:admin,cashier');

    Route::get('inventory-movements', [InventoryMovementController::class, 'index']);
    Route::post('inventory-movements', [InventoryMovementController::class, 'store'])->middleware('role:admin,cashier');

    Route::middleware('role:admin,cashier')->group(function () {
        Route::get('held-orders', [HeldOrderController::class, 'index']);
        Route::post('held-orders', [HeldOrderController::class, 'store']);
        Route::delete('held-orders/{heldOrder}', [HeldOrderController::class, 'destroy']);
    });

    // Shifts (settlement)
    Route::middleware('role:admin,cashier')->group(function () {
        Route::get('shifts', [ShiftController::class, 'index']);
        Route::post('shifts', [ShiftController::class, 'store']);
        Route::get('shifts/{shift}', [ShiftController::class, 'show']);
        Route::post('shifts/{shift}/cash-movements', [ShiftController::class, 'cashMovement']);
        Route::put('shifts/{shift}/close', [ShiftController::class, 'close']);
    });

    // Reports
    Route::get('reports/low-stock', [ReportController::class, 'lowStock'])->middleware('role:admin');
    Route::get('reports/summary', [ReportController::class, 'summary'])->middleware('role:admin');
    Route::get('reports/sales-by-date', [ReportController::class, 'salesByDate'])->middleware('role:admin');
    Route::get('reports/top-products', [ReportController::class, 'topProducts'])->middleware('role:admin');
    Route::get('reports/cashier-performance', [ReportController::class, 'cashierPerformance'])->middleware('role:admin');
    Route::get('reports/profit-by-category', [ReportController::class, 'profitByCategory'])->middleware('role:admin');
    Route::get('reports/stock-valuation', [ReportController::class, 'stockValuation'])->middleware('role:admin');
    Route::get('reports/void-refunds', [ReportController::class, 'voidRefunds'])->middleware('role:admin');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin');
});

