<?php

use App\Modules\Events\Http\Controllers\EventController;
use App\Modules\Events\Http\Controllers\EventStatusController;
use App\Modules\Events\Http\Controllers\EventBrandingController;
use App\Modules\Events\Http\Controllers\EventQrController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Events Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('events', EventController::class);

    // Status actions
    Route::post('events/{event}/publish', [EventStatusController::class, 'publish']);
    Route::post('events/{event}/archive', [EventStatusController::class, 'archive']);

    // Branding
    Route::patch('events/{event}/branding', [EventBrandingController::class, 'update']);

    // QR Code & Share Links
    Route::post('events/{event}/generate-qr', [EventQrController::class, 'generateQr']);
    Route::get('events/{event}/share-links', [EventQrController::class, 'shareLinks']);

    // Moderation settings
    Route::patch('events/{event}/moderation-settings', [EventController::class, 'updateModerationSettings']);
});
