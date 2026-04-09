<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'reason_code' => ['nullable', 'string', 'max:255'],
            'publish_eligibility' => ['nullable', Rule::in(['auto_publish', 'review_only', 'reject'])],
            'effective_media_state' => ['nullable', Rule::in(['published', 'approved', 'pending_moderation', 'rejected'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
