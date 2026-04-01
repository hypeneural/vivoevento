<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Events\WhatsAppInstanceStatusChanged;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Syncs the status of a WhatsApp instance with the provider.
 */
class SyncInstanceStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $instanceId,
    ) {
        $this->onQueue(config('whatsapp.queues.sync', 'whatsapp-sync'));
    }

    public function handle(WhatsAppProviderResolver $providerResolver): void
    {
        $instance = WhatsAppInstance::find($this->instanceId);

        if (! $instance) {
            return;
        }

        try {
            $provider = $providerResolver->forInstance($instance);
            $statusData = $provider->getStatus($instance);

            $previousStatus = $instance->status;
            $newStatus = $statusData->isFullyConnected()
                ? InstanceStatus::Connected
                : InstanceStatus::Disconnected;

            $updates = [
                'status' => $newStatus,
                'last_status_sync_at' => now(),
            ];

            if ($newStatus === InstanceStatus::Connected && $previousStatus !== InstanceStatus::Connected) {
                $updates['connected_at'] = now();
                $updates['disconnected_at'] = null;
            } elseif ($newStatus === InstanceStatus::Disconnected && $previousStatus === InstanceStatus::Connected) {
                $updates['disconnected_at'] = now();
            }

            $instance->update($updates);

            // Dispatch event if status changed
            if ($previousStatus !== $newStatus) {
                WhatsAppInstanceStatusChanged::dispatch($instance, $previousStatus, $newStatus);
            }

            Log::channel('whatsapp')->info('Instance status synced', [
                'instance_id' => $instance->id,
                'previous' => $previousStatus->value,
                'new' => $newStatus->value,
            ]);

        } catch (\Throwable $e) {
            $instance->update([
                'status' => InstanceStatus::Error,
                'last_status_sync_at' => now(),
            ]);

            Log::channel('whatsapp')->error('Instance status sync failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
