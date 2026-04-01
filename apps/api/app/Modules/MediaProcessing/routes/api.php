<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MediaProcessing Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Media management
    Route::get('events/{event}/media', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'index']);
    Route::get('media/{eventMedia}', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'show']);
    Route::post('media/{eventMedia}/approve', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'approve']);
    Route::post('media/{eventMedia}/reject', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'reject']);
    Route::delete('media/{eventMedia}', [\App\Modules\MediaProcessing\Http\Controllers\EventMediaController::class, 'destroy']);
});
