<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('play.manage');
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'slug' => ['sometimes', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
            'ranking_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
