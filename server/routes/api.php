<?php

use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Bills
    Route::prefix('bills')->group(function () {
        Route::post('/preview', [BillController::class, 'preview']);
        Route::post('/', [BillController::class, 'store']);
        Route::post('/{billId}/discount', [BillController::class, 'applyDiscount']);
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::post('/', [PaymentController::class, 'store']);
        Route::post('/{paymentId}/verify', [PaymentController::class, 'verify']);
        Route::post('/{paymentId}/reject', [PaymentController::class, 'reject']);
    });
});
