<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Clients\DTOs\SendReactionData;
use App\Modules\WhatsApp\Models\WhatsAppGroupBinding;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends an automatic reaction to an inbound message based on group binding config.
 *
 * Triggered when:
 * - Inbound message from a group bound to an event
 * - Binding metadata has auto_reaction configured
 */
class SendAutoReactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 5;

    public function __construct(
        public readonly int $messageId,
        public readonly int $bindingId,
    ) {
        $this->onQueue(config('whatsapp.queues.send', 'whatsapp-send'));
    }

    public function handle(WhatsAppMessagingService $messagingService): void
    {
        $message = WhatsAppMessage::with('instance')->find($this->messageId);
        $binding = WhatsAppGroupBinding::find($this->bindingId);

        if (! $message || ! $binding || ! $binding->is_active) {
            return;
        }

        // Get auto-reaction config from binding metadata
        $metadata = $binding->metadata_json ?? [];
        $reaction = $metadata['auto_reaction'] ?? '❤️';
        $enabled = $metadata['auto_reaction_enabled'] ?? false;

        if (! $enabled) {
            return;
        }

        if (! $message->provider_message_id) {
            Log::channel('whatsapp')->warning('Cannot auto-react: no provider_message_id', [
                'message_id' => $message->id,
            ]);
            return;
        }

        $instance = $message->instance;

        $sendData = new SendReactionData(
            phone: $message->sender_phone ?? $message->chat?->external_chat_id ?? '',
            reaction: $reaction,
            messageId: $message->provider_message_id,
        );

        $messagingService->sendReaction($instance, $sendData);

        Log::channel('whatsapp')->info('Auto-reaction sent', [
            'message_id' => $message->id,
            'reaction' => $reaction,
            'binding_id' => $binding->id,
        ]);
    }
}
