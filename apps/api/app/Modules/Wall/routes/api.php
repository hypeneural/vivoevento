<?php

use App\Modules\Wall\Http\Controllers\EventWallController;
use App\Modules\Wall\Http\Controllers\PublicWallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wall Module — API Routes
|--------------------------------------------------------------------------
|
| Admin routes: auth:sanctum middleware
| Public routes: no authentication (wall player access via wall_code)
|
*/

// ─── Admin (auth:sanctum) ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Settings CRUD
    Route::get('events/{event}/wall/settings', [EventWallController::class, 'show'])
        ->whereNumber('event');
    Route::patch('events/{event}/wall/settings', [EventWallController::class, 'update'])
        ->whereNumber('event');

    // Status controls (all instant via broadcast)
    Route::post('events/{event}/wall/start', [EventWallController::class, 'start'])
        ->whereNumber('event');
    Route::post('events/{event}/wall/stop', [EventWallController::class, 'stop'])
        ->whereNumber('event');
    Route::post('events/{event}/wall/pause', [EventWallController::class, 'pause'])
        ->whereNumber('event');
    Route::post('events/{event}/wall/full-stop', [EventWallController::class, 'fullStop'])
        ->whereNumber('event');
    Route::post('events/{event}/wall/expire', [EventWallController::class, 'expire'])
        ->whereNumber('event');
    Route::post('events/{event}/wall/reset', [EventWallController::class, 'reset'])
        ->whereNumber('event');

    // Asset uploads
    Route::post('events/{event}/wall/upload-background', [EventWallController::class, 'uploadBackground'])
        ->whereNumber('event');
    Route::post('events/{event}/wall/upload-logo', [EventWallController::class, 'uploadLogo'])
        ->whereNumber('event');

    // Options (enums for admin forms)
    Route::get('wall/options', [EventWallController::class, 'options']);
});

// ─── Public (wall player — no auth, access via wall_code) ─────────────
Route::prefix('public/wall')->group(function () {
    Route::get('{wallCode}/boot', [PublicWallController::class, 'boot']);
    Route::get('{wallCode}/state', [PublicWallController::class, 'state']);
});
