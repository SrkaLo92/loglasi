<?php

use App\Http\Controllers\Admin\Api\AdminApiController;
use Illuminate\Support\Facades\Route;

// Admin in the mobile app (/api/v1/admin/*): staff only (admin, moderator), same services as /admin.

Route::middleware(['auth:sanctum', 'staff', 'throttle:admin-api'])->prefix('admin')->group(function () {
    Route::get('/listings', [AdminApiController::class, 'listings']);
    Route::post('/listings/{id}/approve', [AdminApiController::class, 'approve'])->whereNumber('id');
    Route::post('/listings/{id}/reject', [AdminApiController::class, 'reject'])->whereNumber('id');
    Route::get('/users', [AdminApiController::class, 'users']);
    Route::post('/users/{id}/verify-phone', [AdminApiController::class, 'verifyPhone'])->whereNumber('id');
    Route::get('/orders', [AdminApiController::class, 'orders']);
    Route::post('/orders/{id}/mark-paid', [AdminApiController::class, 'markPaid'])->whereNumber('id');
    Route::get('/stats', [AdminApiController::class, 'stats']);
});
