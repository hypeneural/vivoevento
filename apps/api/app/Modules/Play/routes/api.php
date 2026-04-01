<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/play/settings', [\App\Modules\Play\Http\Controllers\EventPlayController::class, 'show']);
    Route::patch('events/{event}/play/settings', [\App\Modules\Play\Http\Controllers\EventPlayController::class, 'update']);
    Route::post('events/{event}/play/generate-memory', [\App\Modules\Play\Http\Controllers\EventPlayController::class, 'generateMemory']);
    Route::post('events/{event}/play/generate-puzzle', [\App\Modules\Play\Http\Controllers\EventPlayController::class, 'generatePuzzle']);
});

Route::get('public/events/{event:slug}/play', [\App\Modules\Play\Http\Controllers\PublicPlayController::class, 'manifest']);
