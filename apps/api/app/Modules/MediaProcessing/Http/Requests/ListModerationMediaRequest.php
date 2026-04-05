<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListModerationMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.view') || $this->user()?->can('media.moderate');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([
                'received',
                'processing',
                'pending_moderation',
                'approved',
                'published',
                'rejected',
                'error',
            ])],
            'featured' => ['nullable', 'boolean'],
            'pinned' => ['nullable', 'boolean'],
            'orientation' => ['nullable', Rule::in(['portrait', 'landscape', 'square'])],
        ];
    }
}
