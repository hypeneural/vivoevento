<?php

use App\Modules\Telegram\Http\Controllers\EventTelegramOperationalStatusController;
use App\Modules\Telegram\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function () {
    Route::post('telegram', [TelegramWebhookController::class, 'handle']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/telegram/operational-status', [EventTelegramOperationalStatusController::class, 'show']);
});
