<?php

namespace App\Modules\MediaProcessing\Actions;

use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\MediaProcessing\Services\EventMediaSenderContextService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class UpsertEventMediaSenderBlockAction
{
    public function __construct(
        private readonly EventMediaSenderBlacklistService $blacklists,
        private readonly EventMediaSenderContextService $senderContext,
    ) {}

    public function execute(
        EventMedia $eventMedia,
        ?string $reason = null,
        ?string $expiresAt = null,
    ): EventMediaSenderBlacklist {
        $eventMedia->loadMissing(['event', 'inboundMessage']);

        if (! $this->blacklists->isEnabled($eventMedia->event)) {
            throw ValidationException::withMessages([
                'sender' => 'O bloqueio rapido de remetentes nao esta habilitado para este evento.',
            ]);
        }

        $context = $this->senderContext->resolveFromMedia($eventMedia);

        if (! ($context['available'] ?? false)) {
            throw ValidationException::withMessages([
                'sender' => 'Esta midia nao possui um remetente rastreavel para bloqueio rapido.',
            ]);
        }

        $identityType = (string) ($context['sender_recommended_identity_type'] ?? '');
        $identityValue = (string) ($context['sender_recommended_identity_value'] ?? '');

        if ($identityType === '' || $identityValue === '') {
            throw ValidationException::withMessages([
                'sender' => 'Nao foi possivel determinar um identificador confiavel para o remetente.',
            ]);
        }

        $entry = EventMediaSenderBlacklist::query()->firstOrNew([
            'event_id' => $eventMedia->event_id,
            'identity_type' => $identityType,
            'identity_value' => $identityValue,
        ]);

        $entry->normalized_phone = $context['sender_recommended_normalized_phone'] ?? $entry->normalized_phone;
        $entry->reason = $this->trimString($reason) ?? $entry->reason ?? 'Bloqueado pela moderacao do evento.';
        $entry->expires_at = $expiresAt ? Carbon::parse($expiresAt) : null;
        $entry->is_active = true;
        $entry->save();

        activity()
            ->performedOn($eventMedia)
            ->causedBy(auth()->user())
            ->withProperties([
                'event_id' => $eventMedia->event_id,
                'blacklist_entry_id' => $entry->id,
                'identity_type' => $entry->identity_type,
                'identity_value' => $entry->identity_value,
                'expires_at' => $entry->expires_at?->toIso8601String(),
            ])
            ->log('Remetente bloqueado a partir da moderacao');

        return $entry;
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
