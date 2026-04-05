<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppChatController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppConnectionController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppGroupBindingController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppGroupManagementController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppInstanceController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppLogController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppMessageController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/whatsapp/{provider}/{instanceKey}')->group(function () {
    Route::post('inbound', [WhatsAppWebhookController::class, 'inbound']);
    Route::post('status', [WhatsAppWebhookController::class, 'status']);
    Route::post('delivery', [WhatsAppWebhookController::class, 'delivery']);
});

Route::middleware(['auth:sanctum'])->prefix('whatsapp')->group(function () {
    Route::apiResource('instances', WhatsAppInstanceController::class);

    Route::prefix('instances/{instance}')->group(function () {
        Route::post('test-connection', [WhatsAppInstanceController::class, 'testConnection']);
        Route::post('set-default', [WhatsAppInstanceController::class, 'setDefault']);

        Route::get('status', [WhatsAppConnectionController::class, 'status']);
        Route::get('connection-state', [WhatsAppConnectionController::class, 'connectionState']);
        Route::get('qr-code', [WhatsAppConnectionController::class, 'qrCode']);
        Route::get('qr-code/image', [WhatsAppConnectionController::class, 'qrCodeImage']);
        Route::post('phone-code', [WhatsAppConnectionController::class, 'phoneCode']);
        Route::post('disconnect', [WhatsAppConnectionController::class, 'disconnect']);
        Route::post('sync-status', [WhatsAppConnectionController::class, 'syncStatus']);
    });

    Route::prefix('chats')->group(function () {
        Route::get('/', [WhatsAppChatController::class, 'index']);
        Route::post('find-messages', [WhatsAppChatController::class, 'findMessages']);
        Route::post('modify', [WhatsAppChatController::class, 'modify']);
    });

    Route::prefix('messages')->group(function () {
        Route::get('/', [WhatsAppMessageController::class, 'index']);
        Route::get('{message}', [WhatsAppMessageController::class, 'show']);
        Route::post('text', [WhatsAppMessageController::class, 'sendText']);
        Route::post('image', [WhatsAppMessageController::class, 'sendImage']);
        Route::post('audio', [WhatsAppMessageController::class, 'sendAudio']);
        Route::post('reaction', [WhatsAppMessageController::class, 'sendReaction']);
        Route::post('remove-reaction', [WhatsAppMessageController::class, 'removeReaction']);
        Route::post('carousel', [WhatsAppMessageController::class, 'sendCarousel']);
        Route::post('pix', [WhatsAppMessageController::class, 'sendPixButton']);
    });

    Route::prefix('group-management')->group(function () {
        Route::get('catalog', [WhatsAppGroupManagementController::class, 'catalog']);
        Route::post('create', [WhatsAppGroupManagementController::class, 'create']);

        Route::prefix('{groupId}')->group(function () {
            Route::post('update-name', [WhatsAppGroupManagementController::class, 'updateName']);
            Route::post('update-photo', [WhatsAppGroupManagementController::class, 'updatePhoto']);
            Route::post('update-description', [WhatsAppGroupManagementController::class, 'updateDescription']);
            Route::post('update-settings', [WhatsAppGroupManagementController::class, 'updateSettings']);
            Route::get('invitation-link', [WhatsAppGroupManagementController::class, 'invitationLink']);
            Route::get('participants', [WhatsAppGroupManagementController::class, 'participants']);
            Route::post('add-participants', [WhatsAppGroupManagementController::class, 'addParticipants']);
            Route::post('remove-participants', [WhatsAppGroupManagementController::class, 'removeParticipants']);
            Route::post('promote-admin', [WhatsAppGroupManagementController::class, 'promoteAdmin']);
            Route::post('leave', [WhatsAppGroupManagementController::class, 'leave']);
        });
    });

    Route::prefix('groups')->group(function () {
        Route::get('/', [WhatsAppGroupBindingController::class, 'index']);
        Route::post('{groupId}/bind-event', [WhatsAppGroupBindingController::class, 'bind']);
        Route::patch('{groupId}/binding', [WhatsAppGroupBindingController::class, 'update']);
        Route::delete('{groupId}/binding', [WhatsAppGroupBindingController::class, 'unbind']);
    });

    Route::prefix('logs')->group(function () {
        Route::get('dispatch', [WhatsAppLogController::class, 'dispatch']);
        Route::get('inbound', [WhatsAppLogController::class, 'inbound']);
    });
});
