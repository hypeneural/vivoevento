<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('hub/presets', [\App\Modules\Hub\Http\Controllers\HubPresetController::class, 'index']);
    Route::post('hub/presets', [\App\Modules\Hub\Http\Controllers\HubPresetController::class, 'store']);
    Route::get('events/{event}/hub/settings', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'show']);
    Route::get('events/{event}/hub/insights', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'insights']);
    Route::patch('events/{event}/hub/settings', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'update']);
    Route::post('events/{event}/hub/hero-image', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'uploadHeroImage']);
    Route::post('events/{event}/hub/sponsor-logo', [\App\Modules\Hub\Http\Controllers\EventHubController::class, 'uploadSponsorLogo']);
});

Route::get('public/events/{event}/hub', [\App\Modules\Hub\Http\Controllers\PublicHubController::class, 'index']);
Route::post('public/events/{event}/hub/click', [\App\Modules\Hub\Http\Controllers\PublicHubController::class, 'click']);
