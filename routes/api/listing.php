<?php

use App\Http\Controllers\Listing\Api\FirmApiController;
use App\Http\Controllers\Listing\Api\ListingApiController;
use App\Http\Controllers\Listing\Api\ReportApiController;
use Illuminate\Support\Facades\Route;

// Listing detail, similar listings, seller phone, firm profile and reports (/api/v1).

Route::middleware('throttle:listing-api')->group(function () {
    Route::get('/listings/{id}', [ListingApiController::class, 'show'])->whereNumber('id');
    Route::get('/listings/{id}/similar', [ListingApiController::class, 'similar'])->whereNumber('id');
    Route::get('/firms/{id}', [FirmApiController::class, 'show'])->whereNumber('id');
    Route::get('/home/firms', [FirmApiController::class, 'home']);
    Route::get('/report-reasons', [ReportApiController::class, 'reasons']);
});

// Guests may report a listing too, as on the site; a logged-in reporter is recorded.
Route::post('/listings/{id}/report', [ReportApiController::class, 'listing'])->whereNumber('id')->middleware('throttle:listing-report');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/listings/{id}/contact', [ListingApiController::class, 'contact'])->whereNumber('id')->middleware('throttle:listing-phone');
    Route::post('/reports', [ReportApiController::class, 'store'])->middleware('throttle:listing-report');
});
