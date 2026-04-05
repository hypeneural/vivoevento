<?php

namespace App\Modules\Play\Http\Requests;

use App\Modules\Play\Enums\PlayGameSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowPlayAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('play.view');
    }

    public function rules(): array
    {
        return [
            'play_game_id' => ['nullable', 'integer', 'exists:play_event_games,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => [
                'nullable',
                Rule::in(array_map(static fn (PlayGameSessionStatus $status) => $status->value, PlayGameSessionStatus::cases())),
            ],
            'search' => ['nullable', 'string', 'max:120'],
            'session_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
