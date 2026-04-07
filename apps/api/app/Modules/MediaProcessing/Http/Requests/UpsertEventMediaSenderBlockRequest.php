<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertEventMediaSenderBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
