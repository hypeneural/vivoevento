<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\MediaProcessing\Models\EventMedia;

class DeactivateEventMediaSenderBlockAction
{
    public function execute(EventMedia $eventMedia): ?EventMediaSenderBlacklist
    {
        $eventMedia->loadMissing('inboundMessage');

        $inboundMessage = $eventMedia->inboundMessage;

        if (! $inboundMessage) {
            return null;
        }

        $senderPhone = $this->normalizePhone($inboundMessage->sender_phone);
        $senderLid = $this->trimString($inboundMessage->sender_lid);
        $senderExternalId = $this->trimString($inboundMessage->sender_external_id);

        if (! filled($senderPhone) && ! filled($senderLid) && ! filled($senderExternalId)) {
            return null;
        }

        $entry = EventMediaSenderBlacklist::query()
            ->where('event_id', $eventMedia->event_id)
            ->where('is_active', true)
            ->where(function ($builder) {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($builder) use ($senderExternalId, $senderLid, $senderPhone) {
                if (filled($senderLid)) {
                    $builder->orWhere(function ($lidQuery) use ($senderLid) {
                        $lidQuery
                            ->where('identity_type', 'lid')
                            ->where('identity_value', $senderLid);
                    });
                }

                if (filled($senderExternalId)) {
                    $builder->orWhere(function ($externalIdQuery) use ($senderExternalId) {
                        $externalIdQuery
                            ->where('identity_type', 'external_id')
                            ->where('identity_value', $senderExternalId);
                    });
                }

                if (filled($senderPhone)) {
                    $builder->orWhere(function ($phoneQuery) use ($senderPhone) {
                        $phoneQuery
                            ->where('identity_type', 'phone')
                            ->where(function ($candidateQuery) use ($senderPhone) {
                                $candidateQuery
                                    ->where('identity_value', $senderPhone)
                                    ->orWhere('normalized_phone', $senderPhone);
                            });
                    });
                }
            })
            ->orderByRaw("case identity_type when 'lid' then 1 when 'phone' then 2 else 3 end")
            ->first();

        if (! $entry) {
            return null;
        }

        $entry->forceFill([
            'is_active' => false,
        ])->save();

        activity()
            ->performedOn($eventMedia)
            ->causedBy(auth()->user())
            ->withProperties([
                'event_id' => $eventMedia->event_id,
                'blacklist_entry_id' => $entry->id,
                'identity_type' => $entry->identity_type,
                'identity_value' => $entry->identity_value,
            ])
            ->log('Remetente desbloqueado a partir da moderacao');

        return $entry;
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
