<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| InboundMedia Module Routes
|--------------------------------------------------------------------------
*/

// Webhook endpoints (no auth — validated by signature)
Route::prefix('webhooks')->group(function () {
    // Z-API webhook migrated to WhatsApp module: POST /webhooks/whatsapp/{provider}/{instanceKey}/inbound
    Route::post('telegram', [\App\Modules\InboundMedia\Http\Controllers\TelegramWebhookController::class, 'handle']);
});

// Public upload endpoint (no auth — validated by upload_slug)
Route::get('public/events/{uploadSlug}/upload', [\App\Modules\InboundMedia\Http\Controllers\PublicUploadController::class, 'show']);
Route::post('public/events/{uploadSlug}/upload', [\App\Modules\InboundMedia\Http\Controllers\PublicUploadController::class, 'upload']);
