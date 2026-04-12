<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Enums\EventPersonReviewQueueStatus;
use App\Modules\EventPeople\Jobs\ProjectEventPeopleOperationalCountersJob;
use App\Modules\EventPeople\Models\EventPersonReviewQueueItem;
use App\Modules\EventPeople\Support\EventPeopleStateMachine;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IgnoreEventPersonReviewItemAction
{
    public function __construct(
        private readonly EventPeopleStateMachine $stateMachine,
    ) {}

    /**
     * @return EventPersonReviewQueueItem
     */
    public function execute(
        Event $event,
        EventPersonReviewQueueItem $reviewItem,
        User $user,
        string $resolution = 'ignored',
    ): EventPersonReviewQueueItem {
        if ((int) $reviewItem->event_id !== (int) $event->id) {
            throw ValidationException::withMessages([
                'review_item' => 'O item de revisao nao pertence ao evento.',
            ]);
        }

        return DB::transaction(function () use ($event, $reviewItem, $user, $resolution): EventPersonReviewQueueItem {
            $reviewItem->forceFill([
                'payload' => array_merge($reviewItem->payload ?? [], [
                    'resolution' => $resolution,
                ]),
            ])->save();

            $this->stateMachine->transitionReviewItem($reviewItem, EventPersonReviewQueueStatus::Ignored, [
                'reason' => $resolution,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);

            ProjectEventPeopleOperationalCountersJob::dispatch($event->id);

            return $reviewItem->fresh(['person', 'face']);
        });
    }
}
