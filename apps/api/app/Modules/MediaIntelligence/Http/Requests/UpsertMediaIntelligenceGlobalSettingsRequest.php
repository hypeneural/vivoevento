<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertMediaIntelligenceGlobalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'reply_text_prompt' => ['required', 'string', 'max:5000'],
            'reply_text_fixed_templates' => ['nullable', 'array', 'max:20'],
            'reply_text_fixed_templates.*' => ['string', 'max:500'],
            'reply_prompt_preset_id' => ['nullable', 'integer', 'exists:ai_media_reply_prompt_presets,id'],
            'reply_ai_rate_limit_enabled' => ['nullable', 'boolean'],
            'reply_ai_rate_limit_max_messages' => ['nullable', 'integer', 'min:1', 'max:100'],
            'reply_ai_rate_limit_window_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ];
    }
}
