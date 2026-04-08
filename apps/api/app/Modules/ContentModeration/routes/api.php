<?php

use App\Modules\ContentModeration\Http\Controllers\ContentModerationGlobalSettingsController;
use App\Modules\ContentModeration\Http\Controllers\EventContentModerationSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        'content-moderation/global-settings',
        [ContentModerationGlobalSettingsController::class, 'show'],
    );
    Route::patch(
        'content-moderation/global-settings',
        [ContentModerationGlobalSettingsController::class, 'update'],
    );
    Route::get(
        'events/{event}/content-moderation/settings',
        [EventContentModerationSettingsController::class, 'show'],
    );
    Route::patch(
        'events/{event}/content-moderation/settings',
        [EventContentModerationSettingsController::class, 'update'],
    );
});
