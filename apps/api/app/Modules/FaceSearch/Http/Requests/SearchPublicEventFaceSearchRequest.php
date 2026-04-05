<?php

namespace App\Modules\FaceSearch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchPublicEventFaceSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selfie' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'consent_accepted' => ['required', 'accepted'],
            'consent_version' => ['required', 'string', 'max:80'],
            'selfie_storage_strategy' => ['sometimes', 'string', Rule::in(['memory_only', 'ephemeral_object'])],
        ];
    }
}
