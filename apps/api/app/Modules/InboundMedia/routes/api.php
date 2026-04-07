<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| InboundMedia Module Routes
|--------------------------------------------------------------------------
*/

// Public upload endpoint (no auth - validated by upload_slug)
Route::get('public/events/{uploadSlug}/upload', [\App\Modules\InboundMedia\Http\Controllers\PublicUploadController::class, 'show']);
Route::post('public/events/{uploadSlug}/upload', [\App\Modules\InboundMedia\Http\Controllers\PublicUploadController::class, 'upload']);
