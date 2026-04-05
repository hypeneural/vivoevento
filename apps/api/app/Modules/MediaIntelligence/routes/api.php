<?php

use App\Modules\MediaIntelligence\Http\Controllers\EventMediaIntelligenceSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        'events/{event}/media-intelligence/settings',
        [EventMediaIntelligenceSettingsController::class, 'show'],
    );
    Route::patch(
        'events/{event}/media-intelligence/settings',
        [EventMediaIntelligenceSettingsController::class, 'update'],
    );
});
