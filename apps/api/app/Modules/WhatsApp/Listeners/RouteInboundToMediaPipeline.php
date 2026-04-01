<?php

namespace App\Modules\WhatsApp\Listeners;

use App\Modules\WhatsApp\Enums\GroupBindingType;
use App\Modules\WhatsApp\Events\WhatsAppMessageReceived;
use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listens for inbound WhatsApp messages that have media AND are from a group
 * bound to an event. Routes them to the existing InboundMedia/MediaProcessing pipeline.
 *
 * This is the bridge between the new WhatsApp module and the existing media pipeline.
 */
class RouteInboundToMediaPipeline implements ShouldQueue
{
    public string $queue = 'webhooks';

    public function handle(WhatsAppMessageReceived $event): void
    {
        // Only route if:
        // 1. Has media (image, video, audio, document)
        // 2. Is from a group bound to an event (gallery type)
        if (! $event->hasMedia()) {
            return;
        }

        if (! $event->isBoundToEvent()) {
            return;
        }

        $binding = $event->groupBinding;

        // Only route gallery-type bindings to media pipeline
        if ($binding->binding_type !== GroupBindingType::EventGallery) {
            return;
        }

        Log::channel('whatsapp')->info('Routing inbound media to gallery pipeline', [
            'message_id' => $event->message->id,
            'event_id' => $binding->event_id,
            'media_url' => $event->normalized->mediaUrl,
            'type' => $event->normalized->messageType,
        ]);

        // Dispatch to the existing InboundMedia pipeline
        // The payload format matches what InboundMedia expects
        ProcessInboundWebhookJob::dispatch(
            $event->normalized->providerKey,
            $event->normalized->rawPayload,
        );
    }
}
