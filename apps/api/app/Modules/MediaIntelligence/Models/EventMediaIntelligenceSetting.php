<?php

namespace App\Modules\MediaIntelligence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMediaIntelligenceSetting extends Model
{
    use HasFactory;

    public const DEFAULT_PROMPT_VERSION = 'foundation-v1';

    public const DEFAULT_RESPONSE_SCHEMA_VERSION = 'foundation-v1';

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
            'require_json_output' => true,
        ];
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
        'require_json_output',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'timeout_ms' => 'integer',
        'require_json_output' => 'boolean',
    ];

    protected static function newFactory(): \Database\Factories\EventMediaIntelligenceSettingFactory
    {
        return \Database\Factories\EventMediaIntelligenceSettingFactory::new();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Events\Models\Event::class);
    }
}
