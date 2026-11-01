<?php

use App\Http\Controllers\Info\DraftApiController;
use Illuminate\Support\Facades\Route;

// Info portal: drafts from the off-server news routine (bearer token INFO_DRAFTS_TOKEN) and the
// "Danas u Loznici" data for the apps.

Route::post('/info/drafts', [DraftApiController::class, 'store'])->middleware('throttle:info-drafts')->name('api.info.drafts');
Route::get('/info/today', [DraftApiController::class, 'today'])->name('api.info.today');
