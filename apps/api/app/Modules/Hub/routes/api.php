<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/hub/settings', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'show']);
    Route::patch('events/{event}/hub/settings', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'update']);
});

Route::get('public/events/{event:slug}/hub', [\App\Modules\Hub\Http\Controllers\PublicHubController::class, 'index']);
