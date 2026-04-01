<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/team', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'index']);
    Route::post('events/{event}/team', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'store']);
    Route::patch('events/{event}/team/{member}', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'update']);
    Route::delete('events/{event}/team/{member}', [\App\Modules\EventTeam\Http\Controllers\EventTeamController::class, 'destroy']);
});
