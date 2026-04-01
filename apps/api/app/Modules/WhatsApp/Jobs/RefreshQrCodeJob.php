<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Polls for a fresh QR code during the connection flow.
 *
 * Z-API QR codes expire every ~20 seconds.
 * This job fetches a new one and caches it for the frontend to poll.
 * Max attempts controlled by config('whatsapp.qr_code.max_attempts').
 */
class RefreshQrCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $instanceId,
        public readonly int $attempt = 1,
    ) {
        $this->onQueue(config('whatsapp.queues.sync', 'whatsapp-sync'));
    }

    public function handle(WhatsAppProviderResolver $providerResolver): void
    {
        $instance = WhatsAppInstance::find($this->instanceId);

        if (! $instance) {
            return;
        }

        $maxAttempts = config('whatsapp.qr_code.max_attempts', 3);

        if ($this->attempt > $maxAttempts) {
            Log::channel('whatsapp')->info('QR code refresh max attempts reached', [
                'instance_id' => $instance->id,
                'attempts' => $this->attempt,
            ]);

            Cache::forget("whatsapp:qr:{$instance->id}");
            return;
        }

        try {
            $provider = $providerResolver->forInstance($instance);
            $qrData = $provider->getQrCodeImage($instance);

            if ($qrData->alreadyConnected) {
                // Instance connected — stop polling, sync status
                SyncInstanceStatusJob::dispatch($instance->id);
                Cache::forget("whatsapp:qr:{$instance->id}");
                return;
            }

            // Cache the QR for frontend polling
            $ttl = config('whatsapp.qr_code.poll_interval_seconds', 15) + 5;
            Cache::put("whatsapp:qr:{$instance->id}", [
                'image' => $qrData->qrCodeBase64Image,
                'attempt' => $this->attempt,
                'refreshed_at' => now()->toISOString(),
            ], $ttl);

            // Schedule next refresh
            $delay = config('whatsapp.qr_code.poll_interval_seconds', 15);
            self::dispatch($instance->id, $this->attempt + 1)
                ->delay(now()->addSeconds($delay));

        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('QR code refresh failed', [
                'instance_id' => $instance->id,
                'attempt' => $this->attempt,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
