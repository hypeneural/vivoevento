<?php

namespace App\Modules\Wall\Queries;

use App\Modules\Events\Models\Event;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Collection;

class BuildWallInsightsQuery
{
    public function totals(Event $event): array
    {
        $row = EventMedia::query()
            ->where('event_id', $event->id)
            ->selectRaw('COUNT(*) as received_count')
            ->selectRaw("SUM(CASE WHEN moderation_status = 'approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN publication_status = 'published' THEN 1 ELSE 0 END) as queued_count")
            ->first();

        return [
            'received' => (int) ($row?->received_count ?? 0),
            'approved' => (int) ($row?->approved_count ?? 0),
            'queued' => (int) ($row?->queued_count ?? 0),
            // The current runtime tables only expose current items, not cumulative displayed totals.
            'displayed' => null,
        ];
    }

    public function recentItems(Event $event, int $limit = 10): Collection
    {
        return EventMedia::query()
            ->where('event_id', $event->id)
            ->with([
                'inboundMessage:id,sender_phone,sender_name,sender_avatar_url',
                'variants:id,event_media_id,variant_key,disk,path,width,height,size_bytes,mime_type',
            ])
            ->latest('created_at')
            ->limit($limit)
            ->get([
                'id',
                'event_id',
                'inbound_message_id',
                'uploaded_by_user_id',
                'media_type',
                'source_type',
                'source_label',
                'processing_status',
                'moderation_status',
                'publication_status',
                'is_featured',
                'created_at',
                'published_at',
            ]);
    }

    public function contributorMedia(Event $event): Collection
    {
        return EventMedia::query()
            ->where('event_id', $event->id)
            ->with([
                'inboundMessage:id,sender_phone,sender_name,sender_avatar_url',
            ])
            ->latest('created_at')
            ->get([
                'id',
                'event_id',
                'inbound_message_id',
                'uploaded_by_user_id',
                'source_type',
                'source_label',
                'created_at',
            ]);
    }

    public function sourceMix(Event $event): Collection
    {
        return EventMedia::query()
            ->where('event_id', $event->id)
            ->selectRaw("COALESCE(source_type, 'unknown') as source_type")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('source_type')
            ->get();
    }

    public function lastCaptureAt(Event $event): ?string
    {
        return EventMedia::query()
            ->where('event_id', $event->id)
            ->latest('created_at')
            ->first(['created_at'])
            ?->created_at
            ?->toIso8601String();
    }
}
