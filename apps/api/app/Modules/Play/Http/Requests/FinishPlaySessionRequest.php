<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishPlaySessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $clientResult = $this->input('clientResult', []);

        $this->merge([
            'score' => $this->input('score', data_get($clientResult, 'score')),
            'completed' => $this->input('completed', data_get($clientResult, 'completed', true)),
            'time_ms' => $this->input('time_ms', data_get($clientResult, 'timeMs')),
            'moves' => $this->input('moves', data_get($clientResult, 'moves')),
            'mistakes' => $this->input('mistakes', data_get($clientResult, 'mistakes')),
            'accuracy' => $this->input('accuracy', data_get($clientResult, 'accuracy')),
            'metadata' => $this->input('metadata', data_get($clientResult, 'metadata', $this->input('extra', []))),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:0'],
            'completed' => ['required', 'boolean'],
            'time_ms' => ['required', 'integer', 'min:0'],
            'moves' => ['required', 'integer', 'min:0'],
            'mistakes' => ['nullable', 'integer', 'min:0'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
