<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventPlaySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('play.manage');
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'memory_enabled' => ['sometimes', 'boolean'],
            'puzzle_enabled' => ['sometimes', 'boolean'],
            'memory_card_count' => ['sometimes', 'integer', 'min:2', 'max:20'],
            'puzzle_piece_count' => ['sometimes', 'integer', 'min:4', 'max:25'],
            'auto_refresh_assets' => ['sometimes', 'boolean'],
            'ranking_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
