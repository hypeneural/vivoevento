<?php

namespace App\Modules\Dashboard\Queries;

use App\Modules\Events\Models\Event;
use Illuminate\Support\Facades\Storage;

class RecentEventsQuery
{
    /**
     * Returns the 5 most recently created events for the organization,
     * with photo count and cover image URL.
     */
    public function execute(int $organizationId): array
    {
        $events = Event::query()
            ->where('organization_id', $organizationId)
            ->with('organization:id,trade_name,legal_name,slug')
            ->withCount('media')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'uuid', 'title', 'slug', 'event_type', 'status', 'starts_at', 'cover_image_path', 'organization_id']);

        return $events->map(fn (Event $e) => [
            'id'                => $e->id,
            'uuid'              => $e->uuid,
            'title'             => $e->title,
            'slug'              => $e->slug,
            'event_type'        => $e->event_type?->value,
            'status'            => $e->status?->value,
            'starts_at'         => $e->starts_at?->toISOString(),
            'cover_image_url'   => $e->cover_image_path
                ? (preg_match('/^https?:\/\//i', $e->cover_image_path)
                    ? $e->cover_image_path
                    : Storage::disk('public')->url($e->cover_image_path))
                : null,
            'organization_name' => $e->organization?->trade_name ?? $e->organization?->legal_name,
            'photos_received'   => $e->media_count,
        ])->all();
    }
}
