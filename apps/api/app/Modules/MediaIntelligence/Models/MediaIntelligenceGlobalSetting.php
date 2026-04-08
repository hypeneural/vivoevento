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
        return [
            'reply_text_prompt' => self::defaultReplyTextPrompt(),
            'reply_text_fixed_templates_json' => [],
            'reply_prompt_preset_id' => null,
            'reply_ai_rate_limit_enabled' => false,
            'reply_ai_rate_limit_max_messages' => 10,
            'reply_ai_rate_limit_window_minutes' => 10,
        ];
    }

    protected $fillable = [
        'reply_text_prompt',
        'reply_text_fixed_templates_json',
        'reply_prompt_preset_id',
        'reply_ai_rate_limit_enabled',
        'reply_ai_rate_limit_max_messages',
        'reply_ai_rate_limit_window_minutes',
    ];

    protected $casts = [
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
