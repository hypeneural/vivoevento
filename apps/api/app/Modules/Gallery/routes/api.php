<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/gallery', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'index']);
    Route::post('events/{event}/gallery/{media}/feature', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'feature']);
    Route::delete('events/{event}/gallery/{media}', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'remove']);
});

// Public gallery (no auth)
Route::get('public/events/{event:slug}/gallery', [\App\Modules\Gallery\Http\Controllers\PublicGalleryController::class, 'index']);
