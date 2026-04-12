<?php

namespace App\Modules\Gallery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGalleryBuilderTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('gallery.builder.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPreset = $this->input('event') === 'preset_applied';
        $isAi = $this->input('event') === 'ai_applied';
        $isVitals = $this->input('event') === 'vitals_sample';

        return [
            'event' => [
                'required',
                'string',
                Rule::in(['preset_applied', 'ai_applied', 'vitals_sample']),
            ],

            'preset' => [Rule::requiredIf($isPreset), 'nullable', 'array'],
            'preset.origin_type' => [
                Rule::requiredIf($isPreset),
                'nullable',
                'string',
                Rule::in(['preset', 'shortcut', 'wizard']),
            ],
            'preset.key' => [Rule::requiredIf($isPreset), 'nullable', 'string', 'max:160'],
            'preset.label' => [Rule::requiredIf($isPreset), 'nullable', 'string', 'max:160'],

            'run_id' => [Rule::requiredIf($isAi), 'nullable', 'integer', 'min:1'],
            'variation_id' => [Rule::requiredIf($isAi), 'nullable', 'string', 'max:120'],
            'apply_scope' => [
                Rule::requiredIf($isAi),
                'nullable',
                'string',
                Rule::in(['all', 'theme_tokens', 'page_schema', 'media_behavior']),
            ],

            'viewport' => [Rule::requiredIf($isVitals), 'nullable', 'string', Rule::in(['mobile', 'desktop'])],
            'item_count' => [Rule::requiredIf($isVitals), 'nullable', 'integer', 'min:0', 'max:100000'],
            'layout' => [
                Rule::requiredIf($isVitals),
                'nullable',
                'string',
                Rule::in(['masonry', 'rows', 'columns', 'justified']),
            ],
            'density' => [
                Rule::requiredIf($isVitals),
                'nullable',
                'string',
                Rule::in(['compact', 'comfortable', 'immersive']),
            ],
            'render_mode' => [
                Rule::requiredIf($isVitals),
                'nullable',
                'string',
                Rule::in(['standard', 'optimized']),
            ],
            'lcp_ms' => ['nullable', 'numeric', 'min:0', 'max:60000'],
            'inp_ms' => ['nullable', 'numeric', 'min:0', 'max:60000'],
            'cls' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'preview_latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'publish_latency_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ];
    }
}
