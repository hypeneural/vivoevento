<?php

namespace App\Modules\MediaIntelligence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaIntelligenceGlobalSetting extends Model
{
    use HasFactory;

    public static function defaultReplyTextPrompt(): string
    {
        return 'Gere uma resposta curta em portugues do Brasil, natural, calorosa e coerente com o que aparece na imagem. Use no maximo 2 emojis quando fizer sentido. Nao use hashtags, aspas, frases longas nem invente detalhes. Voce pode mencionar {nome_do_evento} de forma natural apenas se isso combinar com a cena. Se a imagem nao trouxer contexto suficiente para uma resposta segura e adequada, retorne vazio.';
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        $providerKey = (string) config('media_intelligence.default_provider', 'vllm');

        return [
            'enabled' => false,
            'provider_key' => $providerKey,
            'model_key' => (string) config("media_intelligence.providers.{$providerKey}.model", 'Qwen/Qwen2.5-VL-3B-Instruct'),
            'mode' => 'enrich_only',
            'prompt_version' => EventMediaIntelligenceSetting::DEFAULT_PROMPT_VERSION,
            'response_schema_version' => EventMediaIntelligenceSetting::DEFAULT_RESPONSE_SCHEMA_VERSION,
            'timeout_ms' => 12000,
            'fallback_mode' => 'review',
            'context_scope' => 'image_and_text_context',
            'reply_scope' => 'image_and_text_context',
            'normalized_text_context_mode' => 'body_plus_caption',
            'require_json_output' => true,
            'contextual_policy_preset_key' => 'homologacao_livre',
            'policy_version' => 'contextual-policy-v1',
            'allow_alcohol' => true,
            'allow_tobacco' => true,
            'required_people_context' => 'optional',
            'blocked_terms_json' => [],
            'allowed_exceptions_json' => [],
            'freeform_instruction' => null,
            'reply_text_prompt' => self::defaultReplyTextPrompt(),
            'reply_text_fixed_templates_json' => [],
            'reply_prompt_preset_id' => null,
            'reply_ai_rate_limit_enabled' => false,
            'reply_ai_rate_limit_max_messages' => 10,
            'reply_ai_rate_limit_window_minutes' => 10,
        ];
    }

    protected $fillable = [
        'enabled',
        'provider_key',
        'model_key',
        'mode',
        'prompt_version',
        'response_schema_version',
        'timeout_ms',
        'fallback_mode',
        'context_scope',
        'reply_scope',
        'normalized_text_context_mode',
        'require_json_output',
        'contextual_policy_preset_key',
        'policy_version',
        'allow_alcohol',
        'allow_tobacco',
        'required_people_context',
        'blocked_terms_json',
        'allowed_exceptions_json',
        'freeform_instruction',
        'reply_text_prompt',
        'reply_text_fixed_templates_json',
        'reply_prompt_preset_id',
        'reply_ai_rate_limit_enabled',
        'reply_ai_rate_limit_max_messages',
        'reply_ai_rate_limit_window_minutes',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'timeout_ms' => 'integer',
        'require_json_output' => 'boolean',
        'allow_alcohol' => 'boolean',
        'allow_tobacco' => 'boolean',
        'blocked_terms_json' => 'array',
        'allowed_exceptions_json' => 'array',
        'reply_text_fixed_templates_json' => 'array',
        'reply_prompt_preset_id' => 'integer',
        'reply_ai_rate_limit_enabled' => 'boolean',
        'reply_ai_rate_limit_max_messages' => 'integer',
        'reply_ai_rate_limit_window_minutes' => 'integer',
    ];

    public function replyPromptPreset(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MediaReplyPromptPreset::class, 'reply_prompt_preset_id');
    }

    protected static function newFactory(): \Database\Factories\MediaIntelligenceGlobalSettingFactory
    {
        return \Database\Factories\MediaIntelligenceGlobalSettingFactory::new();
    }
}
