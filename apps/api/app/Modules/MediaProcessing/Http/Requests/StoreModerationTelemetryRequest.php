<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModerationTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => [
                'required',
                'string',
                Rule::in([
                    'feed_first_page_loaded',
                    'filters_stabilized',
                    'feed_next_page_loaded',
                    'detail_loaded',
                    'incoming_queue_changed',
                    'media_surface_error',
                    'media_surface_original_fallback',
                    'media_surface_unavailable',
                ]),
            ],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'media_id' => ['nullable', 'integer', 'min:1'],
            'item_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'page_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'queue_size' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'has_more' => ['nullable', 'boolean'],
            'surface_variant' => ['nullable', 'string', Rule::in(['thumbnail', 'preview'])],
            'asset_source' => ['nullable', 'string', 'max:100'],
            'media_type' => ['nullable', 'string', 'max:50'],
            'filters' => ['nullable', 'array'],
            'filters.*' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_null($value) && ! is_scalar($value)) {
                        $fail("The {$attribute} field must be a scalar value.");
                    }
                },
            ],
        ];
    }
}
