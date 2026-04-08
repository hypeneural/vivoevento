<?php

namespace App\Modules\MediaIntelligence\Models;

use App\Modules\MediaIntelligence\Enums\MediaReplyTextMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMediaIntelligenceSetting extends Model
{
    use HasFactory;

    public const DEFAULT_PROMPT_VERSION = 'foundation-v1';

    public const DEFAULT_RESPONSE_SCHEMA_VERSION = 'contextual-v2';

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        $providerKey = (string) config('media_intelligence.default_provider', 'vllm');

        return [
            'provider_key' => $providerKey,
            'model_key' => (string) config("media_intelligence.providers.{$providerKey}.model", 'Qwen/Qwen2.5-VL-3B-Instruct'),
            'enabled' => false,
            'mode' => 'enrich_only',
            'prompt_version' => self::DEFAULT_PROMPT_VERSION,
            'approval_prompt' => self::defaultApprovalPrompt(),
            'caption_style_prompt' => self::defaultCaptionStylePrompt(),
            'response_schema_version' => self::DEFAULT_RESPONSE_SCHEMA_VERSION,
            'timeout_ms' => 12000,
            'fallback_mode' => 'review',
            'context_scope' => 'image_and_text_context',
            'reply_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'contextual_policy_preset_key' => 'homologacao_livre',
            'policy_version' => 'contextual-policy-v1',
            'allow_alcohol' => true,
            'allow_tobacco' => true,
            'required_people_context' => 'optional',
            'blocked_terms_json' => [],
            'allowed_exceptions_json' => [],
            'freeform_instruction' => null,
            'require_json_output' => true,
            'reply_text_enabled' => false,
            'reply_text_mode' => MediaReplyTextMode::Disabled->value,
            'reply_prompt_override' => null,
            'reply_fixed_templates_json' => [],
            'reply_prompt_preset_id' => null,
        ];
    }

    public static function normalizeReplyTextMode(?string $mode, ?bool $legacyEnabled = null): string
    {
        return match ($mode) {
            MediaReplyTextMode::Ai->value => MediaReplyTextMode::Ai->value,
            MediaReplyTextMode::FixedRandom->value => MediaReplyTextMode::FixedRandom->value,
            MediaReplyTextMode::Disabled->value => MediaReplyTextMode::Disabled->value,
            default => MediaReplyTextMode::fromLegacy($legacyEnabled)->value,
        };
    }

    public static function defaultApprovalPrompt(): string
    {
        return 'Avalie se a imagem combina com o contexto do evento, retornando apenas JSON estruturado com decisao, motivo, legenda curta e tags.';
    }

    public static function defaultCaptionStylePrompt(): string
    {
        return 'Gere uma legenda curta, positiva e natural em portugues do Brasil, evitando exageros e frases longas.';
    }

    protected $fillable = [
        'event_id',
        'provider_key',
        'model_key',
        'enabled',
        'mode',
        'prompt_version',
        'approval_prompt',
        'caption_style_prompt',
        'response_schema_version',
        'timeout_ms',
        'fallback_mode',
        'context_scope',
        'reply_scope',
        'normalized_text_context_mode',
        'contextual_policy_preset_key',
        'policy_version',
        'allow_alcohol',
        'allow_tobacco',
        'required_people_context',
        'blocked_terms_json',
        'allowed_exceptions_json',
        'freeform_instruction',
        'require_json_output',
        'reply_text_enabled',
        'reply_text_mode',
        'reply_prompt_override',
        'reply_fixed_templates_json',
        'reply_prompt_preset_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'timeout_ms' => 'integer',
        'allow_alcohol' => 'boolean',
        'allow_tobacco' => 'boolean',
        'blocked_terms_json' => 'array',
        'allowed_exceptions_json' => 'array',
        'require_json_output' => 'boolean',
        'reply_text_enabled' => 'boolean',
        'reply_fixed_templates_json' => 'array',
        'reply_prompt_preset_id' => 'integer',
    ];

    public function automaticReplyEnabled(): bool
    {
        return $this->resolvedReplyTextMode() !== MediaReplyTextMode::Disabled->value;
    }

    public function usesAiAutomaticReply(): bool
    {
        return $this->resolvedReplyTextMode() === MediaReplyTextMode::Ai->value;
    }

    public function usesFixedAutomaticReply(): bool
    {
        return $this->resolvedReplyTextMode() === MediaReplyTextMode::FixedRandom->value;
    }

    public function resolvedReplyTextMode(): string
    {
        $mode = is_string($this->reply_text_mode ?? null) ? $this->reply_text_mode : null;
        $legacyEnabled = (bool) ($this->reply_text_enabled ?? false);

        if ($legacyEnabled && $mode === MediaReplyTextMode::Disabled->value) {
            return MediaReplyTextMode::Ai->value;
        }

        return self::normalizeReplyTextMode($mode, $legacyEnabled);
    }

    public function contextualFreeformInstruction(): ?string
    {
        $freeform = trim((string) ($this->freeform_instruction ?? ''));

        if ($freeform !== '') {
            return $freeform;
        }

        $legacyPrompt = trim((string) ($this->approval_prompt ?? ''));

        return $legacyPrompt !== '' ? $legacyPrompt : null;
    }

    protected static function newFactory(): \Database\Factories\EventMediaIntelligenceSettingFactory
    {
        return \Database\Factories\EventMediaIntelligenceSettingFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }

    public function replyPromptPreset(): BelongsTo
    {
        return $this->belongsTo(MediaReplyPromptPreset::class, 'reply_prompt_preset_id');
    }
}
