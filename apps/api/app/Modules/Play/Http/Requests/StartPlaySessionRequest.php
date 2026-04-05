<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartPlaySessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'player_identifier' => $this->input('player_identifier', $this->input('playerIdentifier')),
            'player_name' => $this->input('player_name', $this->input('displayName')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'player_identifier' => ['required', 'string', 'max:190'],
            'player_name' => ['nullable', 'string', 'max:120'],
            'device' => ['sometimes', 'array'],
            'device.platform' => ['nullable', 'string', 'max:50'],
            'device.viewportWidth' => ['nullable', 'integer', 'min:200', 'max:4096'],
            'device.viewportHeight' => ['nullable', 'integer', 'min:200', 'max:4096'],
            'device.pixelRatio' => ['nullable', 'numeric', 'min:0.5', 'max:5'],
            'device.connection' => ['sometimes', 'array'],
            'device.connection.saveData' => ['nullable', 'boolean'],
            'device.connection.effectiveType' => ['nullable', 'string', 'max:20'],
            'device.connection.downlink' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
