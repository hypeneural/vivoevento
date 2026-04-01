<?php

use App\Modules\Channels\Http\Controllers\EventChannelController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('event-channels', EventChannelController::class);
});
