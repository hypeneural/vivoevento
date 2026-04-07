<?php

namespace App\Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertEventIntakeBlacklistEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer'],
            'identity_type' => ['required', 'string', 'in:phone,lid,external_id'],
            'identity_value' => ['required', 'string', 'max:180'],
            'normalized_phone' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
