<?php

namespace App\Modules\Gallery\Http\Requests;

use App\Modules\Gallery\Support\GalleryBuilderSchemaRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGalleryPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gallery.builder.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:180'],
            'event_type_family' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::EVENT_TYPE_FAMILIES)],
            'style_skin' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::STYLE_SKINS)],
            'behavior_profile' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::BEHAVIOR_PROFILES)],
            'theme_key' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::THEME_KEYS)],
            'layout_key' => ['sometimes', Rule::in(GalleryBuilderSchemaRegistry::LAYOUT_KEYS)],
            'theme_tokens' => ['sometimes', 'array'],
            'page_schema' => ['sometimes', 'array'],
            'media_behavior' => ['sometimes', 'array'],
        ];
    }
}
