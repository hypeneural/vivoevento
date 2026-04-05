<?php

namespace App\Modules\MediaProcessing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventMediaFeaturedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate');
    }

    public function rules(): array
    {
        return [
            'is_featured' => ['required', 'boolean'],
        ];
    }
}
