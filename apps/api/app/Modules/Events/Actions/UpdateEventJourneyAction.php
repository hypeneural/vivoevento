<?php

namespace App\Modules\Events\Actions;

use App\Modules\ContentModeration\Actions\UpsertEventContentModerationSettingsAction;
use App\Modules\Events\Data\EventJourneyProjectionData;
use App\Modules\Events\Models\Event;
use App\Modules\MediaIntelligence\Actions\UpsertEventMediaIntelligenceSettingsAction;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Services\OpenRouterModelPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateEventJourneyAction
{
    public function __construct(
        private readonly UpdateEventAction $updateEvent,
        private readonly UpsertEventContentModerationSettingsAction $upsertContentModeration,
        private readonly UpsertEventMediaIntelligenceSettingsAction $upsertMediaIntelligence,
        private readonly BuildEventJourneyProjectionAction $buildProjection,
        private readonly OpenRouterModelPolicy $openRouterModelPolicy,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(Event $event, array $payload): EventJourneyProjectionData
    {
        DB::transaction(function () use ($event, $payload): void {
            $this->guardModuleEntitlements($event, (array) ($payload['modules'] ?? []));

            $updatedEvent = $this->updateEvent->execute($event, Arr::only($payload, [
                'moderation_mode',
                'modules',
                'intake_defaults',
                'intake_channels',
                'intake_blacklist',
            ]));

            if (is_array($payload['content_moderation'] ?? null)) {
                $this->upsertContentModeration->execute($updatedEvent, $payload['content_moderation']);
            }

            if (is_array($payload['media_intelligence'] ?? null)) {
                $this->guardMediaIntelligencePayload($payload['media_intelligence']);
                $this->upsertMediaIntelligence->execute($updatedEvent, $payload['media_intelligence']);
            }
        });

        /** @var Event $freshEvent */
        $freshEvent = $event->fresh();

        return $this->buildProjection->execute($freshEvent);
    }

    /**
     * @param array<string, mixed> $modules
     */
    private function guardModuleEntitlements(Event $event, array $modules): void
    {
        if (! array_key_exists('wall', $modules) || ! (bool) $modules['wall']) {
            return;
        }

        if ((bool) data_get($event->current_entitlements_json, 'modules.wall', false)) {
            return;
        }

        throw ValidationException::withMessages([
            'modules.wall' => 'O pacote atual do evento nao habilita publicacao no telao.',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function guardMediaIntelligencePayload(array $payload): void
    {
        if ((bool) ($payload['inherit_global'] ?? false)) {
            return;
        }

        $errors = [];
        $mode = (string) ($payload['mode'] ?? 'enrich_only');
        $fallbackMode = (string) ($payload['fallback_mode'] ?? 'review');
        $replyTextMode = EventMediaIntelligenceSetting::normalizeReplyTextMode(
            isset($payload['reply_text_mode']) ? (string) $payload['reply_text_mode'] : null,
            array_key_exists('reply_text_enabled', $payload) ? (bool) $payload['reply_text_enabled'] : null,
        );

        if ($mode === 'gate' && $fallbackMode !== 'review') {
            $errors['media_intelligence.fallback_mode'][] = 'Eventos com VLM em gate devem usar fallback review para nunca aprovar por erro tecnico.';
        }

        if ($replyTextMode === 'fixed_random' && $this->sanitizeTemplates($payload['reply_fixed_templates'] ?? null) === []) {
            $errors['media_intelligence.reply_fixed_templates'][] = 'Informe ao menos uma mensagem pronta para o modo randomico.';
        }

        $providerKey = (string) ($payload['provider_key'] ?? '');
        $requireJsonOutput = (bool) ($payload['require_json_output'] ?? true);

        if ($providerKey === 'openrouter') {
            $error = $this->openRouterModelPolicy->validationError(
                isset($payload['model_key']) ? (string) $payload['model_key'] : null,
                $requireJsonOutput,
            );

            if ($error !== null) {
                $errors['media_intelligence.model_key'][] = $error;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<int, string>
     */
    private function sanitizeTemplates(mixed $templates): array
    {
        if (! is_array($templates)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null,
            $templates,
        )));
    }
}
