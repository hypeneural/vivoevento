<?php

use App\Modules\Play\Http\Controllers\EventPlayController;
use App\Modules\Play\Http\Controllers\EventPlayAnalyticsController;
use App\Modules\Play\Http\Controllers\EventPlayGameController;
use App\Modules\Play\Http\Controllers\PlayCatalogController;
use App\Modules\Play\Http\Controllers\PublicPlayController;
use App\Modules\Play\Http\Controllers\PublicPlayGameController;
use App\Modules\Play\Http\Controllers\PublicPlaySessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('play/catalog', [PlayCatalogController::class, 'index']);

    Route::get('events/{event}/play', [EventPlayController::class, 'manager']);
    Route::get('events/{event}/play/analytics', [EventPlayAnalyticsController::class, 'show']);
    Route::get('events/{event}/play/settings', [EventPlayController::class, 'show']);
    Route::patch('events/{event}/play/settings', [EventPlayController::class, 'update']);

    Route::post('events/{event}/play/games', [EventPlayGameController::class, 'store']);
    Route::patch('events/{event}/play/games/{playGame}', [EventPlayGameController::class, 'update']);
    Route::delete('events/{event}/play/games/{playGame}', [EventPlayGameController::class, 'destroy']);
    Route::get('events/{event}/play/games/{playGame}/assets', [EventPlayGameController::class, 'assets']);
    Route::post('events/{event}/play/games/{playGame}/assets', [EventPlayGameController::class, 'syncAssets']);

    Route::post('events/{event}/play/generate-memory', [EventPlayController::class, 'generateMemory']);
    Route::post('events/{event}/play/generate-puzzle', [EventPlayController::class, 'generatePuzzle']);
});

Route::get('public/events/{event:slug}/play', [PublicPlayController::class, 'manifest']);
Route::get('public/events/{event:slug}/play/{gameSlug}', [PublicPlayGameController::class, 'show']);
Route::get('public/events/{event:slug}/play/{gameSlug}/ranking', [PublicPlayGameController::class, 'ranking']);
Route::get('public/events/{event:slug}/play/{gameSlug}/last-plays', [PublicPlayGameController::class, 'lastPlays']);
Route::post('public/events/{event:slug}/play/{gameSlug}/sessions', [PublicPlaySessionController::class, 'start']);
Route::post('public/play/sessions/{sessionUuid}/moves', [PublicPlaySessionController::class, 'moves']);
Route::post('public/play/sessions/{sessionUuid}/heartbeat', [PublicPlaySessionController::class, 'heartbeat']);
Route::post('public/play/sessions/{sessionUuid}/resume', [PublicPlaySessionController::class, 'resume']);
Route::get('public/play/sessions/{sessionUuid}/analytics', [PublicPlaySessionController::class, 'analytics']);
Route::post('public/play/sessions/{sessionUuid}/finish', [PublicPlaySessionController::class, 'finish']);
