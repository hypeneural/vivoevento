<?php

use App\Modules\Audit\Http\Controllers\AuditController;
use App\Modules\Audit\Http\Controllers\EventTimelineController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('audit', [AuditController::class, 'index']);
    Route::get('audit/filters', [AuditController::class, 'filters']);
    Route::get('audit-logs', [AuditController::class, 'index']);
    Route::get('events/{event}/timeline', [EventTimelineController::class, 'index']);
});
