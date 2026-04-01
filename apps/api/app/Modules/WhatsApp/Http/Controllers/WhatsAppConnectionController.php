<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Jobs\RefreshQrCodeJob;
use App\Modules\WhatsApp\Jobs\SyncInstanceStatusJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WhatsAppConnectionController extends BaseController
{
    public function __construct(
        private readonly WhatsAppProviderResolver $providerResolver,
    ) {}

    /**
     * GET /instances/{instance}/status
     */
    public function status(WhatsAppInstance $instance): JsonResponse
    {
        $provider = $this->providerResolver->forInstance($instance);
        $statusData = $provider->getStatus($instance);

        return $this->success([
            'connected' => $statusData->connected,
            'smartphone_connected' => $statusData->smartphoneConnected,
            'error' => $statusData->error,
            'instance_status' => $instance->status->value,
            'last_sync_at' => $instance->last_status_sync_at?->toISOString(),
        ]);
    }

    /**
     * GET /instances/{instance}/qr-code
     */
    public function qrCode(WhatsAppInstance $instance): JsonResponse
    {
        $provider = $this->providerResolver->forInstance($instance);
        $qrData = $provider->getQrCode($instance);

        if ($qrData->alreadyConnected) {
            return $this->success([
                'already_connected' => true,
                'qr_code' => null,
            ]);
        }

        return $this->success([
            'already_connected' => false,
            'qr_code' => $qrData->qrCodeBytes,
        ]);
    }

    /**
     * GET /instances/{instance}/qr-code/image
     *
     * Also starts the background QR refresh polling.
     */
    public function qrCodeImage(WhatsAppInstance $instance): JsonResponse
    {
        // Check cache first (from background polling)
        $cached = Cache::get("whatsapp:qr:{$instance->id}");

        if ($cached) {
            return $this->success([
                'already_connected' => false,
                'qr_code_base64' => $cached['image'],
                'attempt' => $cached['attempt'],
                'refreshed_at' => $cached['refreshed_at'],
            ]);
        }

        // Fetch fresh QR and start background polling
        $provider = $this->providerResolver->forInstance($instance);
        $qrData = $provider->getQrCodeImage($instance);

        if ($qrData->alreadyConnected) {
            return $this->success([
                'already_connected' => true,
                'qr_code_base64' => null,
            ]);
        }

        // Start background QR refresh
        RefreshQrCodeJob::dispatch($instance->id, 2)
            ->delay(now()->addSeconds(config('whatsapp.qr_code.poll_interval_seconds', 15)));

        return $this->success([
            'already_connected' => false,
            'qr_code_base64' => $qrData->qrCodeBase64Image,
            'attempt' => 1,
            'refreshed_at' => now()->toISOString(),
            'poll_interval_seconds' => config('whatsapp.qr_code.poll_interval_seconds', 15),
            'max_attempts' => config('whatsapp.qr_code.max_attempts', 3),
        ]);
    }

    /**
     * POST /instances/{instance}/phone-code
     */
    public function phoneCode(Request $request, WhatsAppInstance $instance): JsonResponse
    {
        $request->validate(['phone' => 'required|string|min:10']);

        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->requestPhoneCode($instance, $request->input('phone'));

        return $this->success([
            'success' => $result->success,
            'message' => $result->message,
        ]);
    }

    /**
     * POST /instances/{instance}/disconnect
     */
    public function disconnect(WhatsAppInstance $instance): JsonResponse
    {
        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->disconnect($instance);

        if ($result->success) {
            $instance->update([
                'status' => 'disconnected',
                'disconnected_at' => now(),
            ]);
        }

        return $this->success([
            'success' => $result->success,
            'message' => $result->message,
        ]);
    }

    /**
     * POST /instances/{instance}/sync-status
     */
    public function syncStatus(WhatsAppInstance $instance): JsonResponse
    {
        SyncInstanceStatusJob::dispatch($instance->id);

        return $this->success(['message' => 'Status sync queued']);
    }
}
