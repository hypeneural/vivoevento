<?php

namespace App\Modules\Wall\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunWallPlayerCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command' => ['required', Rule::in(['clear-cache', 'revalidate-assets', 'reinitialize-engine'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }
}
