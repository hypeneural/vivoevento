<?php

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\AccessMatrixController;
use App\Modules\Users\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Module Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public
    Route::post('login', [LoginController::class, 'login']);
    Route::post('forgot-password', [LoginController::class, 'forgotPassword']);
    Route::post('reset-password', [LoginController::class, 'resetPassword']);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);
        Route::post('me/avatar', [MeController::class, 'uploadAvatar']);
        Route::delete('me/avatar', [MeController::class, 'deleteAvatar']);
        Route::post('logout', [LoginController::class, 'logout']);
    });
});

// Access Matrix
Route::middleware('auth:sanctum')->group(function () {
    Route::get('access/matrix', [AccessMatrixController::class, 'index']);
});
