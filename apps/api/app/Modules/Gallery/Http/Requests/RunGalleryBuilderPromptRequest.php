<?php

namespace App\Modules\Gallery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunGalleryBuilderPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gallery.builder.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'prompt_text' => ['required', 'string', 'min:8', 'max:500'],
            'persona_key' => ['nullable', 'string', 'max:80'],
            'target_layer' => ['nullable', Rule::in(['mixed', 'theme_tokens', 'page_schema', 'media_behavior'])],
            'base_preset_key' => ['nullable', 'string', 'max:120'],
        ];
    }
}
