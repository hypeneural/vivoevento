<?php

namespace App\Modules\Wall\Http\Requests;

use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWallSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware + policy
    }

    public function rules(): array
    {
        return [
            'layout'             => ['sometimes', Rule::enum(WallLayout::class)],
            'transition_effect'  => ['sometimes', Rule::enum(WallTransition::class)],
            'interval_ms'        => ['sometimes', 'integer', 'min:2000', 'max:60000'],
            'queue_limit'        => ['sometimes', 'integer', 'min:5', 'max:500'],
            'show_qr'            => ['sometimes', 'boolean'],
            'show_branding'      => ['sometimes', 'boolean'],
            'show_neon'          => ['sometimes', 'boolean'],
            'neon_text'          => ['sometimes', 'nullable', 'string', 'max:180'],
            'neon_color'         => ['sometimes', 'nullable', 'string', 'max:30'],
            'show_sender_credit' => ['sometimes', 'boolean'],
            'instructions_text'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'expires_at'         => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'interval_ms.min' => 'O intervalo mínimo é de 2 segundos (2000ms).',
            'interval_ms.max' => 'O intervalo máximo é de 60 segundos (60000ms).',
            'queue_limit.min' => 'O limite mínimo de fotos é 5.',
            'queue_limit.max' => 'O limite máximo de fotos é 500.',
        ];
    }
}
