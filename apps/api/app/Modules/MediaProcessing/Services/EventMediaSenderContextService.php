<?php

namespace App\Modules\MediaProcessing\Services;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class EventMediaSenderContextService
{
    public function hydrateCollection(EloquentCollection $mediaItems, bool $includeMediaCounts = false): void
    {
        if ($mediaItems->isEmpty()) {
            return;
        }

        $mediaItems->loadMissing('inboundMessage');

        $eventStates = Event::query()
            ->whereIn('id', $mediaItems->pluck('event_id')->unique()->filter()->all())
            ->get(['id', 'current_entitlements_json'])
            ->keyBy('id');

        $activeBlacklists = EventMediaSenderBlacklist::query()
            ->whereIn('event_id', $eventStates->keys()->all())
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->groupBy('event_id');

        foreach ($mediaItems as $mediaItem) {
            $context = $this->buildContext(
                $mediaItem,
                $activeBlacklists->get($mediaItem->event_id, collect()),
                $eventStates->get($mediaItem->event_id),
                $includeMediaCounts,
            );

            $this->applyContext($mediaItem, $context);
        }
    }

    public function hydrateModel(EventMedia $eventMedia, bool $includeMediaCount = false): void
    {
        $eventMedia->loadMissing('inboundMessage');

        $eventState = $eventMedia->relationLoaded('event') && $eventMedia->event
            ? $eventMedia->event
            : Event::query()->find($eventMedia->event_id, ['id', 'current_entitlements_json']);

        $activeBlacklists = EventMediaSenderBlacklist::query()
            ->where('event_id', $eventMedia->event_id)
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        $context = $this->buildContext(
            $eventMedia,
            $activeBlacklists,
            $eventState,
            $includeMediaCount,
        );

        $this->applyContext($eventMedia, $context);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventMediaSenderBlacklist>  $activeBlacklists
     * @return array<string, mixed>
     */
    public function buildContext(
        EventMedia $eventMedia,
        \Illuminate\Support\Collection $activeBlacklists,
        ?Event $eventState = null,
        bool $includeMediaCount = false,
    ): array {
        /** @var InboundMessage|null $inboundMessage */
        $inboundMessage = $eventMedia->relationLoaded('inboundMessage')
            ? $eventMedia->inboundMessage
            : $eventMedia->inboundMessage()->first([
                'id',
                'event_id',
                'sender_external_id',
                'sender_phone',
                'sender_lid',
                'sender_name',
                'sender_avatar_url',
            ]);

        if (! $inboundMessage) {
            return $this->emptyContext($eventState);
        }

        $senderExternalId = $this->trimString($inboundMessage->sender_external_id);
        $senderPhone = $this->normalizePhone($inboundMessage->sender_phone);
        $senderLid = $this->trimString($inboundMessage->sender_lid);

        if (! filled($senderExternalId) && ! filled($senderPhone) && ! filled($senderLid)) {
            return $this->emptyContext($eventState, $inboundMessage);
        }

        [$recommendedType, $recommendedValue] = $this->resolveRecommendedIdentity(
            senderLid: $senderLid,
            senderPhone: $senderPhone,
            senderExternalId: $senderExternalId,
        );

        $blockingEntry = $this->findBlockingEntry(
            $activeBlacklists,
            senderExternalId: $senderExternalId,
            senderPhone: $senderPhone,
            senderLid: $senderLid,
        );

        return [
            'available' => true,
            'sender_external_id' => $senderExternalId,
            'sender_phone' => $senderPhone,
            'sender_lid' => $senderLid,
            'sender_name' => $this->trimString($inboundMessage->sender_name),
            'sender_avatar_url' => $this->trimString($inboundMessage->sender_avatar_url),
            'sender_blocked' => $blockingEntry !== null,
            'sender_blocking_entry_id' => $blockingEntry?->id,
            'sender_block_reason' => $blockingEntry?->reason,
            'sender_block_expires_at' => $blockingEntry?->expires_at?->toIso8601String(),
            'sender_recommended_identity_type' => $recommendedType,
            'sender_recommended_identity_value' => $recommendedValue,
            'sender_recommended_normalized_phone' => $recommendedType === 'phone' ? $senderPhone : null,
            'sender_blacklist_enabled' => (bool) data_get($eventState?->current_entitlements_json, 'channels.blacklist.enabled', false),
            'sender_media_count' => $includeMediaCount
                ? $this->countMediaForSender(
                    eventId: $eventMedia->event_id,
                    identityType: $recommendedType,
                    identityValue: $recommendedValue,
                    normalizedPhone: $senderPhone,
                )
                : null,
        ];
    }

    public function resolveFromMedia(EventMedia $eventMedia): array
    {
        $eventState = $eventMedia->relationLoaded('event') && $eventMedia->event
            ? $eventMedia->event
            : Event::query()->find($eventMedia->event_id, ['id', 'current_entitlements_json']);

        $activeBlacklists = EventMediaSenderBlacklist::query()
            ->where('event_id', $eventMedia->event_id)
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        return $this->buildContext($eventMedia, $activeBlacklists, $eventState, true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function applyContext(EventMedia $eventMedia, array $context): void
    {
        foreach ($context as $key => $value) {
            $eventMedia->setAttribute($key, $value);
        }
    }

    private function emptyContext(?Event $eventState = null, ?InboundMessage $inboundMessage = null): array
    {
        return [
            'available' => false,
            'sender_external_id' => $this->trimString($inboundMessage?->sender_external_id),
            'sender_phone' => $this->normalizePhone($inboundMessage?->sender_phone),
            'sender_lid' => $this->trimString($inboundMessage?->sender_lid),
            'sender_name' => $this->trimString($inboundMessage?->sender_name),
            'sender_avatar_url' => $this->trimString($inboundMessage?->sender_avatar_url),
            'sender_blocked' => false,
            'sender_blocking_entry_id' => null,
            'sender_block_reason' => null,
            'sender_block_expires_at' => null,
            'sender_recommended_identity_type' => null,
            'sender_recommended_identity_value' => null,
            'sender_recommended_normalized_phone' => null,
            'sender_blacklist_enabled' => (bool) data_get($eventState?->current_entitlements_json, 'channels.blacklist.enabled', false),
            'sender_media_count' => null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, EventMediaSenderBlacklist>  $activeBlacklists
     */
    private function findBlockingEntry(
        \Illuminate\Support\Collection $activeBlacklists,
        ?string $senderExternalId,
        ?string $senderPhone,
        ?string $senderLid,
    ): ?EventMediaSenderBlacklist {
        return $activeBlacklists->first(function (EventMediaSenderBlacklist $entry) use ($senderExternalId, $senderPhone, $senderLid) {
            return match ($entry->identity_type) {
                'lid' => filled($senderLid) && $entry->identity_value === $senderLid,
                'external_id' => filled($senderExternalId) && $entry->identity_value === $senderExternalId,
                'phone' => filled($senderPhone)
                    && ($entry->identity_value === $senderPhone || $entry->normalized_phone === $senderPhone),
                default => false,
            };
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRecommendedIdentity(?string $senderLid, ?string $senderPhone, ?string $senderExternalId): array
    {
        if (filled($senderLid)) {
            return ['lid', $senderLid];
        }

        if (filled($senderPhone)) {
            return ['phone', $senderPhone];
        }

        return ['external_id', (string) $senderExternalId];
    }

    private function countMediaForSender(
        int $eventId,
        string $identityType,
        string $identityValue,
        ?string $normalizedPhone,
    ): int {
        return EventMedia::query()
            ->where('event_id', $eventId)
            ->whereHas('inboundMessage', function ($builder) use ($identityType, $identityValue, $normalizedPhone) {
                match ($identityType) {
                    'lid' => $builder->where('sender_lid', $identityValue),
                    'external_id' => $builder->where('sender_external_id', $identityValue),
                    'phone' => $builder->where(function ($phoneQuery) use ($identityValue, $normalizedPhone) {
                        $phoneQuery->where('sender_phone', $identityValue);

                        if (filled($normalizedPhone) && $normalizedPhone !== $identityValue) {
                            $phoneQuery->orWhere('sender_phone', $normalizedPhone);
                        }
                    }),
                    default => null,
                };
            })
            ->count();
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function trimString(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
