<?php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Custom route BEFORE apiResource to avoid route conflict with plans/{plan}
    Route::get('plans/current', [\App\Modules\Billing\Http\Controllers\SubscriptionController::class, 'currentPlan']);

    Route::apiResource('plans', \App\Modules\Plans\Http\Controllers\PlanController::class);
});
