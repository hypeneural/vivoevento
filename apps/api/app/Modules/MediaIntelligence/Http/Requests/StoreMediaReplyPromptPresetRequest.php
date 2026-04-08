<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaReplyPromptPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'platform-admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('ai_media_reply_prompt_presets', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80', 'exists:ai_media_reply_prompt_categories,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'prompt_template' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
