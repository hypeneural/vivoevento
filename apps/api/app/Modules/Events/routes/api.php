<?php

use App\Modules\Events\Http\Controllers\EventController;
use App\Modules\Events\Http\Controllers\EventJourneyController;
use App\Modules\Events\Http\Controllers\EventIntakeBlacklistController;
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
    Route::get('events/{event}/journey-builder', [EventJourneyController::class, 'show']);
    Route::patch('events/{event}/journey-builder', [EventJourneyController::class, 'update']);
    Route::get('events/{event}/commercial-status', [EventController::class, 'commercialStatus']);

    // Status actions
    Route::post('events/{event}/publish', [EventStatusController::class, 'publish']);
    Route::post('events/{event}/archive', [EventStatusController::class, 'archive']);

    // Branding
    Route::post('events/branding-assets', [EventBrandingController::class, 'storeAsset']);
    Route::patch('events/{event}/branding', [EventBrandingController::class, 'update']);

    // QR Code & Share Links
    Route::post('events/{event}/generate-qr', [EventQrController::class, 'generateQr']);
    Route::get('events/{event}/share-links', [EventQrController::class, 'shareLinks']);
    Route::patch('events/{event}/public-links', [EventQrController::class, 'updateIdentifiers']);
    Route::post('events/{event}/public-links/regenerate', [EventQrController::class, 'regenerateIdentifiers']);

    // Moderation settings
    Route::patch('events/{event}/moderation-settings', [EventController::class, 'updateModerationSettings']);
    Route::post('events/{event}/intake-blacklist/entries', [EventIntakeBlacklistController::class, 'store']);
    Route::delete('events/{event}/intake-blacklist/entries/{entry}', [EventIntakeBlacklistController::class, 'destroy']);
});
