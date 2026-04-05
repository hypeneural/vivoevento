<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkModerationIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct', 'exists:event_media,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
