<?php

namespace App\Modules\Telegram\Jobs;

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Services\TelegramFeedbackAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramFeedbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly int $eventId,
        public readonly array $context,
        public readonly string $phase,
        public readonly ?int $inboundMessageId = null,
        public readonly ?int $eventMediaId = null,
    ) {
        $this->onQueue('telegram-send');
    }

    public function handle(): void
    {
        $event = Event::query()->with('modules')->find($this->eventId);

        if (! $event) {
            return;
        }

        $inboundMessage = $this->inboundMessageId !== null
            ? InboundMessage::query()->find($this->inboundMessageId)
            : null;
        $eventMedia = $this->eventMediaId !== null
            ? EventMedia::query()->find($this->eventMediaId)
            : null;

        $feedback = app(TelegramFeedbackAutomationService::class);

        match ($this->phase) {
            'session_activated' => $feedback->sendSessionActivatedFeedback($event, $this->context),
            'session_closed' => $feedback->sendSessionClosedFeedback($event, $this->context),
            'blocked' => $feedback->sendBlockedFeedback($event, $this->context, $inboundMessage, $eventMedia),
            'detected' => $feedback->sendDetectedFeedback($event, $this->context, $inboundMessage),
            'published' => $feedback->sendPublishedFeedback($event, $this->context, $inboundMessage, $eventMedia),
            'rejected' => $feedback->sendRejectedFeedback($event, $this->context, $inboundMessage, $eventMedia),
            default => null,
        };
    }
}
