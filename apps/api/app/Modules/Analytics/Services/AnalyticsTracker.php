<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Models\AnalyticsEvent;
use App\Modules\Events\Models\Event;
use Illuminate\Http\Request;
use Throwable;

class AnalyticsTracker
{
    public function trackEvent(
        Event $event,
        string $eventName,
        Request $request,
        array $metadata = [],
        ?int $eventMediaId = null,
        ?string $channel = null,
    ): void {
        try {
            $occurredAt = now();
            $user = $request->user();

            AnalyticsEvent::query()->create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'event_media_id' => $eventMediaId,
                'event_name' => $eventName,
                'actor_type' => $user ? 'user' : 'guest',
                'actor_id' => $user
                    ? (string) $user->getAuthIdentifier()
                    : (string) ($request->ip() ?: 'guest'),
                'channel' => $channel,
                'metadata_json' => $this->metadata($request, $metadata),
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function metadata(Request $request, array $metadata): array
    {
        return array_filter([
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            ...$metadata,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
