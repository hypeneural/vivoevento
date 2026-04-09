<?php

use App\Modules\FaceSearch\Http\Controllers\EventFaceSearchSearchController;
use App\Modules\FaceSearch\Http\Controllers\EventFaceSearchOperationsController;
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
    Route::get(
        'events/{event}/face-search/health',
        [EventFaceSearchOperationsController::class, 'health'],
    );
    Route::post(
        'events/{event}/face-search/reindex',
        [EventFaceSearchOperationsController::class, 'reindex'],
    );
    Route::post(
        'events/{event}/face-search/reconcile',
        [EventFaceSearchOperationsController::class, 'reconcile'],
    );
    Route::delete(
        'events/{event}/face-search/collection',
        [EventFaceSearchOperationsController::class, 'deleteCollection'],
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
