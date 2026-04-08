<?php

namespace App\Modules\MediaIntelligence\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMediaReplyPromptTestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'provider_key' => ['nullable', 'string', 'in:vllm,openrouter,noop'],
            'status' => ['nullable', 'string', 'in:success,failed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
