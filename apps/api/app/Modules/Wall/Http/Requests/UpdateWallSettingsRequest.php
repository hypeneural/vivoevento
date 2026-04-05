<?php

namespace App\Modules\Wall\Http\Requests;

use App\Modules\Wall\Enums\WallLayout;
use App\Modules\Wall\Enums\WallEventPhase;
use App\Modules\Wall\Enums\WallSelectionMode;
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
            'layout' => ['sometimes', Rule::enum(WallLayout::class)],
            'transition_effect' => ['sometimes', Rule::enum(WallTransition::class)],
            'interval_ms' => ['sometimes', 'integer', 'min:2000', 'max:60000'],
            'queue_limit' => ['sometimes', 'integer', 'min:5', 'max:500'],
            'selection_mode' => ['sometimes', Rule::enum(WallSelectionMode::class)],
            'event_phase' => ['sometimes', Rule::enum(WallEventPhase::class)],
            'selection_policy' => ['sometimes', 'array'],
            'selection_policy.max_eligible_items_per_sender' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'selection_policy.max_replays_per_item' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'selection_policy.low_volume_max_items' => ['sometimes', 'integer', 'min:2', 'max:20'],
            'selection_policy.medium_volume_max_items' => ['sometimes', 'integer', 'min:3', 'max:50'],
            'selection_policy.replay_interval_low_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'selection_policy.replay_interval_medium_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'selection_policy.replay_interval_high_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'selection_policy.sender_cooldown_seconds' => ['sometimes', 'integer', 'min:0', 'max:300'],
            'selection_policy.sender_window_limit' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'selection_policy.sender_window_minutes' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'selection_policy.avoid_same_sender_if_alternative_exists' => ['sometimes', 'boolean'],
            'selection_policy.avoid_same_duplicate_cluster_if_alternative_exists' => ['sometimes', 'boolean'],
            'show_qr' => ['sometimes', 'boolean'],
            'show_branding' => ['sometimes', 'boolean'],
            'show_neon' => ['sometimes', 'boolean'],
            'neon_text' => ['sometimes', 'nullable', 'string', 'max:180'],
            'neon_color' => ['sometimes', 'nullable', 'string', 'max:30'],
            'show_sender_credit' => ['sometimes', 'boolean'],
            'instructions_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'interval_ms.min' => 'O intervalo minimo e de 2 segundos (2000ms).',
            'interval_ms.max' => 'O intervalo maximo e de 60 segundos (60000ms).',
            'queue_limit.min' => 'O limite minimo de fotos e 5.',
            'queue_limit.max' => 'O limite maximo de fotos e 500.',
            'selection_policy.max_eligible_items_per_sender.max' => 'Use no maximo 12 midias elegiveis por remetente.',
            'selection_policy.max_replays_per_item.max' => 'Use no maximo 6 repeticoes por item.',
            'selection_policy.low_volume_max_items.max' => 'Use no maximo 20 itens para a faixa de fila baixa.',
            'selection_policy.medium_volume_max_items.max' => 'Use no maximo 50 itens para a faixa de fila media.',
        ];
    }
}
