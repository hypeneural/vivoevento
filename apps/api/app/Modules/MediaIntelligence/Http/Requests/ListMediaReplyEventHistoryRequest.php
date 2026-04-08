<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMediaReplyEventHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'min:1'],
            'provider_key' => ['nullable', 'string', 'max:100'],
            'model_key' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'preset_name' => ['nullable', 'string', 'max:255'],
            'sender_query' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
