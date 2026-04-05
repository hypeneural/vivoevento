<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('media.view');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::in([
                'received',
                'processing',
                'pending_moderation',
                'approved',
                'published',
                'rejected',
                'error',
            ])],
        ];
    }
}
