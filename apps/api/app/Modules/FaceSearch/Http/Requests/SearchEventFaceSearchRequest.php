<?php

namespace App\Modules\FaceSearch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchEventFaceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.moderate') ?? false;
    }

    public function rules(): array
    {
        return [
            'selfie' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'include_pending' => ['sometimes', 'boolean'],
        ];
    }
}
