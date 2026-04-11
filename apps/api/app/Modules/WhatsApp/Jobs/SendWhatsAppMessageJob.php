<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\WhatsAppMessageSent;
use App\Modules\WhatsApp\Exceptions\MessageSendFailedException;
use App\Modules\WhatsApp\Models\WhatsAppDispatchLog;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use App\Modules\WhatsApp\Support\WhatsAppLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a WhatsApp message via the provider adapter.
 *
 * Flow:
 * 1. Load message & instance
 * 2. Mark as sending
 * 3. Resolve provider adapter
 * 4. Call the appropriate send method
 * 5. Update message with provider IDs
 * 6. Create dispatch log
 * 7. Dispatch WhatsAppMessageSent event
 */
class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $messageId,
        public readonly string $method,
        public readonly object $sendData,
    ) {
        $this->onQueue(config('whatsapp.queues.send', 'whatsapp-send'));
    }

    public function handle(WhatsAppProviderResolver $providerResolver): void
    {
        $message = WhatsAppMessage::with('instance')->findOrFail($this->messageId);
        $instance = $message->instance;

        // Mark as sending
        $message->update(['status' => MessageStatus::Sending]);

        $startTime = microtime(true);

        try {
            // Resolve provider
            $provider = $providerResolver->forInstance($instance);

            // Call the appropriate send method
            $result = $provider->{$this->method}($instance, $this->sendData);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($result->success) {
                // Update message with provider IDs
                $message->update([
                    'status' => MessageStatus::Sent,
                    'provider_message_id' => $result->providerMessageId,
                    'provider_zaap_id' => $result->providerZaapId,
                    'sent_at' => now(),
                ]);

                // Dispatch success event
                WhatsAppMessageSent::dispatch($message->fresh());
            } else {
                $message->update([
                    'status' => MessageStatus::Failed,
                    'failed_at' => now(),
                ]);
            }

            // Create dispatch log
            $this->createDispatchLog($instance, $message, $durationMs, $result->success, [
                'provider_message_id' => $result->providerMessageId,
                'provider_zaap_id' => $result->providerZaapId,
                'raw' => $result->rawResponse,
            ], $result->httpStatus, $result->success ? null : $result->error);

            if (! $result->success) {
                throw new MessageSendFailedException(
                    message: $result->error ?? 'Provider returned failure',
                    httpStatus: $result->httpStatus,
                    providerResponse: $result->rawResponse,
                );
            }

        } catch (MessageSendFailedException $e) {
            throw $e; // Re-throw for retry
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $message->update([
                'status' => MessageStatus::Failed,
                'failed_at' => now(),
            ]);

            $this->createDispatchLog($instance, $message, $durationMs, false, [], null, $e->getMessage());

            WhatsAppLog::error('Message send failed', [
                'message_id' => $message->id,
                'instance_id' => $instance->id,
                'method' => $this->method,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function createDispatchLog(
        $instance,
        WhatsAppMessage $message,
        int $durationMs,
        bool $success,
        array $responseJson,
        ?int $httpStatus,
        ?string $error,
    ): void {
        WhatsAppDispatchLog::create([
            'instance_id' => $instance->id,
            'message_id' => $message->id,
            'provider_key' => $instance->provider_key->value,
            'endpoint_used' => $this->method,
            'request_json' => $message->payload_json ?? [],
            'response_json' => $responseJson,
            'http_status' => $httpStatus,
            'success' => $success,
            'error_message' => $error,
            'duration_ms' => $durationMs,
        ]);
    }
}
