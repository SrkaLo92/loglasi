<?php

use App\Http\Controllers\Listing\Api\FirmApiController;
use App\Http\Controllers\Listing\Api\ListingApiController;
use App\Http\Controllers\Listing\Api\ReportApiController;
use App\Http\Controllers\Reports\Api\MyReportApiController;
use Illuminate\Support\Facades\Route;

// Listing detail, similar listings, seller phone, firm profile and reports (/api/v1).

Route::middleware('throttle:listing-api')->group(function () {
    Route::get('/listings/{id}', [ListingApiController::class, 'show'])->whereNumber('id');
    Route::get('/listings/{id}/similar', [ListingApiController::class, 'similar'])->whereNumber('id');
    Route::get('/firms/{id}', [FirmApiController::class, 'show'])->whereNumber('id');
    Route::get('/home/firms', [FirmApiController::class, 'home']);
    Route::get('/report-reasons', [ReportApiController::class, 'reasons']);
});

// Reporting needs an account (the controller answers a guest with 401 and a clear message).
Route::post('/listings/{id}/report', [ReportApiController::class, 'listing'])->whereNumber('id')->middleware('throttle:listing-report');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/listings/{id}/contact', [ListingApiController::class, 'contact'])->whereNumber('id')->middleware('throttle:listing-phone');
    Route::post('/reports', [ReportApiController::class, 'store'])->middleware('throttle:listing-report');

    // The thread of a report with the admin (question from the admin, the reporter's answers).
    Route::get('/me/reports/{report}', [MyReportApiController::class, 'show'])->whereNumber('report')->middleware('throttle:60,1');
    Route::post('/me/reports/{report}/messages', [MyReportApiController::class, 'reply'])->whereNumber('report')->middleware('throttle:notifications-account');
});
