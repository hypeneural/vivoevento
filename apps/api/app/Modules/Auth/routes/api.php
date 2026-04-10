<?php

use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\AccessMatrixController;
use App\Modules\Auth\Http\Controllers\AccessPresetController;
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
    Route::post('register/request-otp', [LoginController::class, 'requestRegisterOtp']);
    Route::post('register/resend-otp', [LoginController::class, 'resendRegisterOtp']);
    Route::post('register/verify-otp', [LoginController::class, 'verifyRegisterOtp']);
    Route::post('forgot-password', [LoginController::class, 'forgotPassword']);
    Route::post('forgot-password/resend-otp', [LoginController::class, 'resendForgotPasswordOtp']);
    Route::post('forgot-password/verify-otp', [LoginController::class, 'verifyForgotPasswordOtp']);
    Route::post('reset-password', [LoginController::class, 'resetPassword']);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);
        Route::post('context/organization', [MeController::class, 'setOrganizationContext']);
        Route::post('context/event', [MeController::class, 'setEventContext']);
        Route::patch('me/password', [MeController::class, 'updatePassword']);
        Route::post('me/avatar', [MeController::class, 'uploadAvatar']);
        Route::delete('me/avatar', [MeController::class, 'deleteAvatar']);
        Route::post('logout', [LoginController::class, 'logout']);
    });
});

// Access Matrix
Route::middleware('auth:sanctum')->group(function () {
    Route::get('access/matrix', [AccessMatrixController::class, 'index']);
    Route::get('access/presets', [AccessPresetController::class, 'index']);
});
