<?php

namespace App\Modules\EventOperations\Listeners;

use App\Modules\EventOperations\Actions\AppendEventOperationEventAction;
use App\Modules\EventOperations\Support\EventOperationsEventMapper;
use App\Modules\Events\Models\Event;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use App\Modules\WhatsApp\Models\WhatsAppMessageFeedback;
use Illuminate\Support\Arr;

class ProjectFeedbackToOperations
{
    public function __construct(
        private readonly EventOperationsEventMapper $mapper,
        private readonly AppendEventOperationEventAction $append,
    ) {}

    public function handleWhatsAppFeedback(WhatsAppMessageFeedback $feedback): void
    {
        if (! $this->shouldProjectFeedback($feedback->wasRecentlyCreated, $feedback->wasChanged('status'))) {
            return;
        }

        $mapped = $this->mapper->fromWhatsAppFeedback($feedback);
        $parentEvent = Event::query()->find($feedback->event_id);

        if (! $parentEvent || ! $mapped) {
            return;
        }

        $this->append->execute($parentEvent, Arr::except($mapped, ['priority']));
    }

    public function handleTelegramFeedback(TelegramMessageFeedback $feedback): void
    {
        if (! $this->shouldProjectFeedback($feedback->wasRecentlyCreated, $feedback->wasChanged('status'))) {
            return;
        }

        $mapped = $this->mapper->fromTelegramFeedback($feedback);
        $parentEvent = Event::query()->find($feedback->event_id);

        if (! $parentEvent || ! $mapped) {
            return;
        }

        $this->append->execute($parentEvent, Arr::except($mapped, ['priority']));
    }

    private function shouldProjectFeedback(bool $wasRecentlyCreated, bool $statusChanged): bool
    {
        return $wasRecentlyCreated || $statusChanged;
    }
}
