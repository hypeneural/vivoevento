<?php

namespace App\Modules\Events\Services;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\WhatsApp\Clients\DTOs\NormalizedInboundMessageData;

class EventMediaSenderBlacklistService
{
    public function matchNormalized(Event $event, NormalizedInboundMessageData $normalized): ?EventMediaSenderBlacklist
    {
        return $this->matchCandidates($event, $this->candidatesFromNormalized($normalized));
    }

    /**
     * @param  array{
     *   lid?: array<int, string|null>,
     *   external_id?: array<int, string|null>,
     *   phone?: array<int, string|null>
     * }  $candidates
     */
    public function matchCandidates(Event $event, array $candidates): ?EventMediaSenderBlacklist
    {
        if (! $this->isEnabled($event)) {
            return null;
        }

        $query = EventMediaSenderBlacklist::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        foreach ($candidates as $identityType => $values) {
            $candidateValues = array_values(array_unique(array_filter(array_map(function (?string $value) use ($identityType) {
                if ($identityType === 'phone') {
                    return $this->normalizePhone($value);
                }

                return is_string($value) ? trim($value) : null;
            }, $values), fn (?string $value) => filled($value))));

            if ($candidateValues === []) {
                continue;
            }

            $match = (clone $query)
                ->where('identity_type', $identityType)
                ->whereIn('identity_value', $candidateValues)
                ->first();

            if ($match) {
                return $match;
            }
        }

        $phoneCandidates = array_values(array_unique(array_filter($candidates['phone'] ?? [])));

        if ($phoneCandidates === []) {
            return null;
        }

        return (clone $query)
            ->whereNotNull('normalized_phone')
            ->whereIn('normalized_phone', $phoneCandidates)
            ->first();
    }

    public function isEnabled(Event $event): bool
    {
        return (bool) data_get($event->current_entitlements_json, 'channels.blacklist.enabled', false);
    }

    /**
     * @return array{lid: array<int, string|null>, external_id: array<int, string|null>, phone: array<int, string|null>}
     */
    private function candidatesFromNormalized(NormalizedInboundMessageData $normalized): array
    {
        return [
            'lid' => [
                $normalized->participantLid,
            ],
            'external_id' => [
                $normalized->senderExternalId(),
                $normalized->chatId,
            ],
            'phone' => [
                $this->normalizePhone($normalized->participantPhone),
                $this->normalizePhone($normalized->senderPhone),
            ],
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
}
