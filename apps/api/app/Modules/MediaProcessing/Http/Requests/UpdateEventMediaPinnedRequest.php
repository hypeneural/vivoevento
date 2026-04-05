<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventMediaPinnedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate');
    }

    public function rules(): array
    {
        return [
            'is_pinned' => ['required', 'boolean'],
        ];
    }
}
