<?php

namespace App\Modules\Hub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackPublicHubClickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'button_id' => ['nullable', 'string', 'max:80'],
        ];
    }
}
