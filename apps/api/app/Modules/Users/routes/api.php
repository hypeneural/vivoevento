<?php

use App\Modules\Users\Http\Controllers\MeController;
use App\Modules\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Users Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // User management (admin)
    Route::apiResource('users', UserController::class)->only(['index', 'show']);

    // My activity
    Route::get('users/me/activity', [\App\Modules\Users\Http\Controllers\UserActivityController::class, 'index']);
});
