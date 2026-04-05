<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlayMovesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $moves = collect($this->input('moves', []))
            ->map(fn ($move) => [
                'move_number' => data_get($move, 'move_number', data_get($move, 'moveNumber')),
                'move_type' => data_get($move, 'move_type', data_get($move, 'type')),
                'payload' => data_get($move, 'payload', []),
                'occurred_at' => data_get($move, 'occurred_at', data_get($move, 'occurredAt')),
            ])
            ->all();

        $payload = [
            'moves' => $moves,
        ];

        if ($this->has('batch_number') || $this->has('batchNumber')) {
            $payload['batch_number'] = $this->input('batch_number', $this->input('batchNumber'));
        }

        $this->merge($payload);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_number' => ['sometimes', 'integer', 'min:1'],
            'moves' => ['required', 'array', 'min:1', 'max:50'],
            'moves.*.move_number' => ['required', 'integer', 'min:1'],
            'moves.*.move_type' => ['required', 'string', 'max:40'],
            'moves.*.payload' => ['sometimes', 'array'],
            'moves.*.occurred_at' => ['nullable', 'date'],
        ];
    }
}
