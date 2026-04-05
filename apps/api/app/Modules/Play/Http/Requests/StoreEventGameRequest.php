<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('play.manage');
    }

    public function rules(): array
    {
        return [
            'game_type_key' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
            'ranking_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
