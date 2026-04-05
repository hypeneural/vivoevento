<?php

namespace App\Modules\MediaProcessing\Queries;

use App\Modules\MediaProcessing\Enums\ModerationStatus;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Database\Eloquent\Builder;

class ListEventMediaQuery
{
    public function __construct(
        private readonly int $eventId,
        private readonly ?string $status = null,
    ) {}

    public function query(): Builder
    {
        return EventMedia::query()
            ->where('event_id', $this->eventId)
            ->with(['variants', 'inboundMessage'])
            ->latest()
            ->when($this->status, function (Builder $builder, string $status) {
                match ($status) {
                    'published' => $builder->where('publication_status', PublicationStatus::Published),
                    'approved' => $builder->where('moderation_status', ModerationStatus::Approved),
                    'rejected' => $builder->where('moderation_status', ModerationStatus::Rejected),
                    'pending_moderation' => $builder->where('moderation_status', ModerationStatus::Pending),
                    'processing' => $builder->whereIn('processing_status', ['downloaded', 'processed']),
                    'error' => $builder->where('processing_status', 'failed'),
                    default => $builder->where('processing_status', 'received'),
                };
            });
    }
}
