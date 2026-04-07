<?php

use App\Modules\Partners\Http\Controllers\PartnerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('partners', [PartnerController::class, 'index']);
    Route::post('partners', [PartnerController::class, 'store']);
    Route::get('partners/{partner}', [PartnerController::class, 'show']);
    Route::patch('partners/{partner}', [PartnerController::class, 'update']);
    Route::delete('partners/{partner}', [PartnerController::class, 'destroy']);
    Route::post('partners/{partner}/suspend', [PartnerController::class, 'suspend']);
    Route::get('partners/{partner}/events', [PartnerController::class, 'events']);
    Route::get('partners/{partner}/clients', [PartnerController::class, 'clients']);
    Route::get('partners/{partner}/staff', [PartnerController::class, 'staff']);
    Route::post('partners/{partner}/staff', [PartnerController::class, 'storeStaff']);
    Route::get('partners/{partner}/grants', [PartnerController::class, 'grants']);
    Route::post('partners/{partner}/grants', [PartnerController::class, 'storeGrant']);
    Route::get('partners/{partner}/activity', [PartnerController::class, 'activity']);
});
