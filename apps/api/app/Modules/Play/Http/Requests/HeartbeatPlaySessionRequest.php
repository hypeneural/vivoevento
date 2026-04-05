<?php

namespace App\Modules\Play\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeartbeatPlaySessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'elapsed_ms' => $this->input('elapsed_ms', $this->input('elapsedMs')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', 'string', Rule::in(['visible', 'hidden', 'backgrounded'])],
            'reason' => ['nullable', 'string', 'max:50'],
            'elapsed_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
