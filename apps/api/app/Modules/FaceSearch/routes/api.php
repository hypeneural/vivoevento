<?php

use App\Modules\FaceSearch\Http\Controllers\EventFaceSearchSearchController;
use App\Modules\FaceSearch\Http\Controllers\EventFaceSearchSettingsController;
use App\Modules\FaceSearch\Http\Controllers\PublicEventFaceSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        'events/{event}/face-search/settings',
        [EventFaceSearchSettingsController::class, 'show'],
    );
    Route::patch(
        'events/{event}/face-search/settings',
        [EventFaceSearchSettingsController::class, 'update'],
    );
    Route::post(
        'events/{event}/face-search/search',
        [EventFaceSearchSearchController::class, 'store'],
    );
});

Route::get(
    'public/events/{event:slug}/face-search',
    [PublicEventFaceSearchController::class, 'show'],
);
Route::post(
    'public/events/{event:slug}/face-search/search',
    [PublicEventFaceSearchController::class, 'store'],
)->middleware('throttle:public-face-search');
