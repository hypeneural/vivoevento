<?php

namespace App\Modules\MediaIntelligence\Services;

use App\Modules\MediaIntelligence\Models\MediaIntelligenceGlobalSetting;
use App\Modules\MediaProcessing\Models\EventMedia;

class PublishedMediaReplyTextResolver
{
    public function __construct(
        private readonly FixedMediaReplyTextTemplateSelector $selector,
    ) {}

    public function resolve(EventMedia $media, ?string $fallbackReplyText = null): ?string
    {
        return $this->resolveContext($media, $fallbackReplyText)['reply_text'] ?? null;
    }

    /**
     * @return array{
     *   mode:string,
     *   source:string,
     *   reply_text:string,
     *   template:string|null,
     *   variables:array<string,string>,
     *   evaluation_id:int|null
     * }|null
     */
    public function resolveContext(EventMedia $media, ?string $fallbackReplyText = null): ?array
    {
        $media->loadMissing('event.mediaIntelligenceSettings', 'latestVlmEvaluation', 'inboundMessage');

        $settings = $media->event?->mediaIntelligenceSettings;

        if (! $settings?->automaticReplyEnabled()) {
            return null;
        }

        if ($settings->usesFixedAutomaticReply()) {
            return $this->resolveFixedReplyText($media);
        }

        $replyText = trim((string) ($fallbackReplyText ?: ($media->latestVlmEvaluation?->reply_text ?? '')));

        return $replyText === '' ? null : [
            'mode' => 'ai',
            'source' => 'vlm',
            'reply_text' => $replyText,
            'template' => null,
            'variables' => [
                'nome_do_evento' => trim((string) ($media->event?->title ?? '')),
            ],
            'evaluation_id' => $media->latestVlmEvaluation?->id,
        ];
    }

    /**
     * @return array{
     *   mode:string,
     *   source:string,
     *   reply_text:string,
     *   template:string|null,
     *   variables:array<string,string>,
     *   evaluation_id:int|null
     * }|null
     */
    private function resolveFixedReplyText(EventMedia $media): ?array
    {
        $settings = $media->event?->mediaIntelligenceSettings;
        $templates = $settings?->reply_fixed_templates_json ?? [];
        $source = 'event_fixed_template';

        if (! is_array($templates) || $templates === []) {
            $global = MediaIntelligenceGlobalSetting::query()->firstOrNew(
                ['id' => 1],
                MediaIntelligenceGlobalSetting::defaultAttributes(),
            );

            $templates = $global->reply_text_fixed_templates_json ?? [];
            $source = 'global_fixed_template';
        }

        if (! is_array($templates) || $templates === []) {
            return null;
        }

        $seed = implode('|', array_filter([
            (string) $media->id,
            (string) ($media->inboundMessage?->provider_message_id ?? ''),
            (string) ($media->inboundMessage?->message_id ?? ''),
        ]));

        $selected = $this->selector->pick($templates, $seed !== '' ? $seed : (string) $media->id);

        if ($selected === null) {
            return null;
        }

        $eventTitle = trim((string) ($media->event?->title ?? ''));
        $variables = [
            'nome_do_evento' => $eventTitle,
        ];
        $resolved = str_replace('{nome_do_evento}', $eventTitle, $selected);
        $resolved = preg_replace('/\s+/u', ' ', trim($resolved)) ?: '';

        return $resolved !== '' ? [
            'mode' => 'fixed_random',
            'source' => $source,
            'reply_text' => $resolved,
            'template' => $selected,
            'variables' => $variables,
            'evaluation_id' => null,
        ] : null;
    }
}
