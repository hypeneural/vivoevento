<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('audit-logs', [\App\Modules\Audit\Http\Controllers\AuditController::class, 'index']);
    Route::get('events/{event}/timeline', [\App\Modules\Audit\Http\Controllers\EventTimelineController::class, 'index']);
});
