<?php

namespace App\Modules\Events\Support;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\InboundMedia\Models\InboundMessage;
use Illuminate\Support\Collection;

class EventIntakeBlacklistStateBuilder
{
    public function __construct(
        private readonly EventMediaSenderBlacklistService $blacklists,
    ) {}

    /**
     * @return array{
     *   intake_blacklist: array{
     *     enabled: bool,
     *     entries: array<int, array<string, mixed>>,
     *     senders: array<int, array<string, mixed>>
     *   }
     * }
     */
    public function build(Event $event): array
    {
        $event->loadMissing('mediaSenderBlacklists');

        $entries = $event->mediaSenderBlacklists
            ->sortByDesc(fn (EventMediaSenderBlacklist $entry) => [
                $entry->isCurrentlyActive(),
                $entry->updated_at?->timestamp ?? 0,
                $entry->id,
            ])
            ->values();

        return [
            'intake_blacklist' => [
                'enabled' => $this->blacklists->isEnabled($event),
                'entries' => $entries
                    ->map(fn (EventMediaSenderBlacklist $entry) => $this->mapEntry($entry))
                    ->all(),
                'senders' => $this->buildSenderSummaries($event, $entries),
            ],
        ];
    }

    /**
     * @param Collection<int, EventMediaSenderBlacklist> $entries
     * @return array<int, array<string, mixed>>
     */
    private function buildSenderSummaries(Event $event, Collection $entries): array
    {
        $messages = InboundMessage::query()
            ->where('event_id', $event->id)
            ->with('eventMedia:id,inbound_message_id')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'event_id',
                'message_id',
                'chat_external_id',
                'sender_external_id',
                'sender_phone',
                'sender_lid',
                'sender_name',
                'sender_avatar_url',
                'normalized_payload_json',
                'received_at',
            ]);

        $summaries = [];

        foreach ($messages as $message) {
            $context = is_array($message->normalized_payload_json)
                ? (array) data_get($message->normalized_payload_json, '_event_context', [])
                : [];

            $senderExternalId = $this->trimString($message->sender_external_id)
                ?? $this->trimString(data_get($context, 'sender_external_id'))
                ?? $this->trimString($message->sender_lid)
                ?? $this->trimString(data_get($context, 'sender_lid'))
                ?? $this->normalizePhone($message->sender_phone)
                ?? $this->normalizePhone(data_get($context, 'sender_phone'))
                ?? $this->trimString($message->chat_external_id)
                ?? $this->trimString(data_get($context, 'chat_external_id'));

            $senderPhone = $this->normalizePhone($message->sender_phone)
                ?? $this->normalizePhone(data_get($context, 'sender_phone'));
            $senderLid = $this->trimString($message->sender_lid)
                ?? $this->trimString(data_get($context, 'sender_lid'));

            if (! filled($senderExternalId) && ! filled($senderPhone) && ! filled($senderLid)) {
                continue;
            }

            $key = $senderExternalId ?? $senderLid ?? $senderPhone ?? "inbound:{$message->id}";

            if (! isset($summaries[$key])) {
                [$recommendedType, $recommendedValue] = $this->resolveRecommendedIdentity(
                    $senderLid,
                    $senderPhone,
                    $senderExternalId,
                );

                $summaries[$key] = [
                    'sender_external_id' => $senderExternalId,
                    'sender_phone' => $senderPhone,
                    'sender_lid' => $senderLid,
                    'sender_name' => $this->trimString($message->sender_name) ?? $this->trimString(data_get($context, 'sender_name')),
                    'sender_avatar_url' => $this->trimString($message->sender_avatar_url)
                        ?? $this->trimString(data_get($context, 'sender_avatar_url'))
                        ?? $this->trimString(data_get($message->normalized_payload_json, 'senderPhoto')),
                    'inbound_count' => 0,
                    'media_count' => 0,
                    'last_seen_at' => $message->received_at?->toISOString(),
                    'recommended_identity_type' => $recommendedType,
                    'recommended_identity_value' => $recommendedValue,
                    'recommended_normalized_phone' => $recommendedType === 'phone' ? $senderPhone : null,
                    'blocked' => false,
                    'blocking_entry_id' => null,
                    'blocking_expires_at' => null,
                    'blocking_reason' => null,
                ];
            }

            $summaries[$key]['inbound_count']++;
            $summaries[$key]['media_count'] += $message->eventMedia !== null ? 1 : 0;

            if (! filled($summaries[$key]['sender_name'])) {
                $summaries[$key]['sender_name'] = $this->trimString($message->sender_name)
                    ?? $this->trimString(data_get($context, 'sender_name'));
            }

            if (! filled($summaries[$key]['sender_avatar_url'])) {
                $summaries[$key]['sender_avatar_url'] = $this->trimString($message->sender_avatar_url)
                    ?? $this->trimString(data_get($context, 'sender_avatar_url'))
                    ?? $this->trimString(data_get($message->normalized_payload_json, 'senderPhoto'));
            }
        }

        foreach ($summaries as &$summary) {
            $match = $this->matchEntryForSummary($entries, $summary);

            if (! $match) {
                continue;
            }

            $summary['blocked'] = $match->isCurrentlyActive();
            $summary['blocking_entry_id'] = $match->id;
            $summary['blocking_expires_at'] = $match->expires_at?->toISOString();
            $summary['blocking_reason'] = $match->reason;
        }
        unset($summary);

        uasort($summaries, function (array $left, array $right): int {
            $leftLastSeen = strtotime((string) ($left['last_seen_at'] ?? '')) ?: 0;
            $rightLastSeen = strtotime((string) ($right['last_seen_at'] ?? '')) ?: 0;

            return [$rightLastSeen, $right['media_count'], $right['inbound_count']]
                <=> [$leftLastSeen, $left['media_count'], $left['inbound_count']];
        });

        return array_values($summaries);
    }

    /**
     * @param Collection<int, EventMediaSenderBlacklist> $entries
     */
    private function matchEntryForSummary(Collection $entries, array $summary): ?EventMediaSenderBlacklist
    {
        $candidates = [
            'lid' => array_values(array_filter([
                $summary['sender_lid'] ?? null,
            ])),
            'external_id' => array_values(array_filter([
                $summary['sender_external_id'] ?? null,
            ])),
            'phone' => array_values(array_filter([
                $summary['sender_phone'] ?? null,
            ])),
        ];

        foreach ($candidates as $identityType => $values) {
            foreach ($values as $value) {
                $match = $entries->first(
                    fn (EventMediaSenderBlacklist $entry) => $entry->identity_type === $identityType
                        && $entry->identity_value === $value
                );

                if ($match) {
                    return $match;
                }
            }
        }

        $phone = $summary['sender_phone'] ?? null;

        if (! filled($phone)) {
            return null;
        }

        return $entries->first(
            fn (EventMediaSenderBlacklist $entry) => filled($entry->normalized_phone)
                && $entry->normalized_phone === $phone
        );
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

    /**
     * @return array<string, mixed>
     */
    private function mapEntry(EventMediaSenderBlacklist $entry): array
    {
        return [
            'id' => $entry->id,
            'identity_type' => $entry->identity_type,
            'identity_value' => $entry->identity_value,
            'normalized_phone' => $entry->normalized_phone,
            'reason' => $entry->reason,
            'expires_at' => $entry->expires_at?->toISOString(),
            'is_active' => (bool) $entry->is_active,
            'is_currently_blocking' => $entry->isCurrentlyActive(),
            'created_at' => $entry->created_at?->toISOString(),
            'updated_at' => $entry->updated_at?->toISOString(),
        ];
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function trimString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
