<?php

namespace App\Modules\Events\Http\Requests;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('events.view');
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'status' => ['nullable', Rule::in(array_map(static fn (EventStatus $status) => $status->value, EventStatus::cases()))],
            'event_type' => ['nullable', Rule::in(array_map(static fn (EventType $type) => $type->value, EventType::cases()))],
            'module' => ['nullable', 'string', Rule::in(['live', 'wall', 'play', 'hub'])],
            'search' => ['nullable', 'string', 'max:180'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', Rule::in(['starts_at', 'created_at', 'title', 'status'])],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
