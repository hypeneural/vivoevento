<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('clients', \App\Modules\Clients\Http\Controllers\ClientController::class);
    Route::get('clients/{client}/events', [\App\Modules\Clients\Http\Controllers\ClientController::class, 'events']);
});
