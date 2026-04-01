<?php
use Illuminate\Support\Facades\Route;
Route::middleware('auth:sanctum')->group(function () {
    Route::get('analytics/events/{event}', [\App\Modules\Analytics\Http\Controllers\AnalyticsController::class, 'eventOverview']);
    Route::get('analytics/platform', [\App\Modules\Analytics\Http\Controllers\AnalyticsController::class, 'platformOverview']);
});
