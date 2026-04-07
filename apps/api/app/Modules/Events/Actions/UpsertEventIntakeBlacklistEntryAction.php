<?php

namespace App\Modules\Events\Actions;

use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\Events\Services\EventMediaSenderBlacklistService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class UpsertEventIntakeBlacklistEntryAction
{
    public function __construct(
        private readonly EventMediaSenderBlacklistService $blacklists,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public function execute(Event $event, array $entry): EventMediaSenderBlacklist
    {
        if (! $this->blacklists->isEnabled($event)) {
            throw ValidationException::withMessages([
                'sender' => 'A blacklist de remetentes nao esta habilitada para este evento.',
            ]);
        }

        $payload = $this->normalizeEntry($entry);
        $model = $this->resolveTargetModel($event, $entry, $payload);

        $model->fill($payload);
        $model->event()->associate($event);
        $model->save();

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function normalizeEntry(array $entry): array
    {
        $identityType = (string) ($entry['identity_type'] ?? 'external_id');
        $identityValue = trim((string) ($entry['identity_value'] ?? ''));

        if ($identityValue === '') {
            throw ValidationException::withMessages([
                'sender' => 'Cada bloqueio precisa informar um identificador valido.',
            ]);
        }

        $normalizedPhone = $this->normalizePhone(
            $identityType === 'phone'
                ? $identityValue
                : (is_string($entry['normalized_phone'] ?? null) ? $entry['normalized_phone'] : null)
        );

        return [
            'identity_type' => $identityType,
            'identity_value' => $identityValue,
            'normalized_phone' => $normalizedPhone,
            'reason' => $this->trimString($entry['reason'] ?? null),
            'expires_at' => $this->parseExpiresAt($entry['expires_at'] ?? null),
            'is_active' => (bool) ($entry['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $payload
     */
    private function resolveTargetModel(Event $event, array $entry, array $payload): EventMediaSenderBlacklist
    {
        $entryId = $entry['id'] ?? null;

        if (is_numeric($entryId)) {
            $model = EventMediaSenderBlacklist::query()
                ->where('event_id', $event->id)
                ->find((int) $entryId);

            if ($model) {
                return $model;
            }
        }

        return EventMediaSenderBlacklist::query()->firstOrNew([
            'event_id' => $event->id,
            'identity_type' => $payload['identity_type'],
            'identity_value' => $payload['identity_value'],
        ]);
    }

    private function normalizePhone(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^0-9]/', '', $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function parseExpiresAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
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
