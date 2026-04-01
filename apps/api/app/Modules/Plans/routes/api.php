<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('plans', \App\Modules\Plans\Http\Controllers\PlanController::class);
});
