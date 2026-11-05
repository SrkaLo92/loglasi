<?php

use App\Http\Controllers\Messages\Api\ConversationApiController;
use App\Http\Controllers\Messages\Api\FavoriteApiController;
use App\Http\Controllers\Users\Api\BlockApiController;
use Illuminate\Support\Facades\Route;

// Messages and favourites in the mobile app (/api/v1). Rules: docs/modules/messages.md.

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ConversationApiController::class, 'index']);
    Route::post('/conversations', [ConversationApiController::class, 'store'])->middleware('throttle:messages-send');
    Route::get('/conversations/{conversation}/messages', [ConversationApiController::class, 'messages'])->whereNumber('conversation');
    Route::post('/conversations/{conversation}/messages', [ConversationApiController::class, 'reply'])->whereNumber('conversation')->middleware('throttle:messages-send');

    Route::get('/me/blocks', [BlockApiController::class, 'index']);
    Route::post('/users/{id}/block', [BlockApiController::class, 'store'])->whereNumber('id')->middleware('throttle:messages-favorites');
    Route::delete('/users/{id}/block', [BlockApiController::class, 'destroy'])->whereNumber('id')->middleware('throttle:messages-favorites');

    Route::get('/me/favorites', [FavoriteApiController::class, 'index']);
    Route::put('/me/favorites/{id}', [FavoriteApiController::class, 'update'])->whereNumber('id')->middleware('throttle:messages-favorites');
    Route::delete('/me/favorites/{id}', [FavoriteApiController::class, 'destroy'])->whereNumber('id')->middleware('throttle:messages-favorites');
});
