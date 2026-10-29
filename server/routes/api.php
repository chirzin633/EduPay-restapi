<?php

use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\StudentController;
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

    // Refunds
    Route::prefix('refunds')->group(function () {
        Route::post('/', [RefundController::class, 'store']);
        Route::post('/{refundId}/submit', [RefundController::class, 'submit']);
        Route::post('/{refundId}/approve', [RefundController::class, 'approve']);
        Route::post('/{refundId}/reject', [RefundController::class, 'reject']);
        Route::post('/{refundId}/complete', [RefundController::class, 'complete']);
    });

    // Students
    Route::prefix('students')->group(function () {
        Route::get('/', [StudentController::class, 'index']);
        Route::post('/import', [StudentController::class, 'import']);
        Route::post('/', [StudentController::class, 'store']);
        Route::get('/{student}', [StudentController::class, 'show']);
        Route::put('/{student}', [StudentController::class, 'update']);
        Route::post('/{student}/change-status', [StudentController::class, 'changeStatus']);
    });
});
