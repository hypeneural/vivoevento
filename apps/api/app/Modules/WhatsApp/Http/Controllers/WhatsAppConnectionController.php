<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Http\Requests\RequestPhoneCodeRequest;
use App\Modules\WhatsApp\Jobs\SyncInstanceStatusJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppConnectionStateService;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;

class WhatsAppConnectionController extends BaseController
{
    public function __construct(
        private readonly WhatsAppProviderResolver $providerResolver,
        private readonly WhatsAppConnectionStateService $connectionStateService,
    ) {}

    public function status(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $status = $provider->getStatus($instance);

        return $this->success([
            'connected' => $status->connected,
            'smartphone_connected' => $status->smartphoneConnected,
            'error' => $status->error,
            'instance_status' => $instance->normalizedStatus()->value,
            'last_sync_at' => $instance->last_status_sync_at?->toISOString(),
        ]);
    }

    public function connectionState(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        return $this->success($this->connectionStateService->build($instance));
    }

    public function qrCode(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $qrData = $provider->getQrCode($instance);

        return $this->success([
            'already_connected' => $qrData->alreadyConnected,
            'qr_code' => $qrData->payload(),
            'qr_render_mode' => $qrData->renderMode(),
            'error' => $qrData->error,
        ]);
    }

    public function qrCodeImage(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        $state = $this->connectionStateService->build($instance);

        return $this->success([
            'already_connected' => $state['connected'],
            'qr_code' => $state['qr_code'],
            'qr_render_mode' => $state['qr_render_mode'],
            'qr_available' => $state['qr_available'],
            'qr_expires_in_sec' => $state['qr_expires_in_sec'],
            'qr_error' => $state['qr_error'],
            'checked_at' => $state['checked_at'],
        ]);
    }

    public function phoneCode(RequestPhoneCodeRequest $request, WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('update', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->requestPhoneCode($instance, $request->validated('phone'));

        return $this->success([
            'success' => $result->success,
            'message' => $result->message,
            'raw' => $result->rawResponse,
        ]);
    }

    public function disconnect(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('update', $instance);

        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->disconnect($instance);

        if ($result->success) {
            $instance->update([
                'status' => 'disconnected',
                'last_status_sync_at' => now(),
                'last_health_check_at' => now(),
                'last_health_status' => 'disconnected',
                'last_error' => null,
                'disconnected_at' => now(),
            ]);
        }

        return $this->success([
            'success' => $result->success,
            'message' => $result->message,
        ]);
    }

    public function syncStatus(WhatsAppInstance $instance): JsonResponse
    {
        $this->authorize('update', $instance);

        SyncInstanceStatusJob::dispatch($instance->id);

        return $this->success(['message' => 'Status sync queued']);
    }
}
