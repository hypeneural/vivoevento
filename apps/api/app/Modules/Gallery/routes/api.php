<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('gallery', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'catalogIndex']);
    Route::get('gallery/presets', [\App\Modules\Gallery\Http\Controllers\GalleryPresetController::class, 'index']);
    Route::post('gallery/presets', [\App\Modules\Gallery\Http\Controllers\GalleryPresetController::class, 'store']);
    Route::get('events/{event}/gallery', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'index']);
    Route::post('events/{event}/gallery/{media}/publish', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'publish']);
    Route::post('events/{event}/gallery/{media}/feature', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'feature']);
    Route::delete('events/{event}/gallery/{media}', [\App\Modules\Gallery\Http\Controllers\GalleryMediaController::class, 'remove']);
    Route::get('events/{event}/gallery/settings', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'show']);
    Route::patch('events/{event}/gallery/settings', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'update']);
    Route::post('events/{event}/gallery/autosave', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'autosave']);
    Route::post('events/{event}/gallery/publish', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'publish']);
    Route::get('events/{event}/gallery/revisions', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'revisions']);
    Route::post('events/{event}/gallery/revisions/{revision}/restore', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'restore']);
    Route::post('events/{event}/gallery/preview-link', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'previewLink']);
    Route::post('events/{event}/gallery/hero-image', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'uploadHeroImage']);
    Route::post('events/{event}/gallery/banner-image', [\App\Modules\Gallery\Http\Controllers\GalleryBuilderController::class, 'uploadBannerImage']);
});

// Public gallery (no auth)
Route::get('public/events/{event:slug}/gallery', [\App\Modules\Gallery\Http\Controllers\PublicGalleryController::class, 'index']);
Route::get('public/events/{event:slug}/gallery/media', [\App\Modules\Gallery\Http\Controllers\PublicGalleryController::class, 'media']);
Route::get('public/gallery-previews/{token}', [\App\Modules\Gallery\Http\Controllers\PublicGalleryController::class, 'preview']);
