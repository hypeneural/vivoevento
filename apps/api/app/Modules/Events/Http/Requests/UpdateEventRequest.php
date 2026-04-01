<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:180'],
            'visibility' => ['nullable', 'string', 'in:public,private,unlisted'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'location_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
