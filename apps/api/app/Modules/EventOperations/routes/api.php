<?php

use App\Modules\EventOperations\Http\Controllers\EventOperationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/operations/room', [EventOperationsController::class, 'room']);
    Route::get('events/{event}/operations/timeline', [EventOperationsController::class, 'timeline']);
});
