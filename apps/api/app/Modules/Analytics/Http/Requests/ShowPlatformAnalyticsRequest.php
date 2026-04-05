<?php

namespace App\Modules\Analytics\Http\Requests;

use App\Modules\Events\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowPlatformAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('analytics.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['7d', '30d', '90d', 'custom'])],
            'date_from' => [
                Rule::requiredIf(fn () => $this->input('period', '30d') === 'custom'),
                'nullable',
                'date',
            ],
            'date_to' => [
                Rule::requiredIf(fn () => $this->input('period', '30d') === 'custom'),
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'event_status' => ['nullable', Rule::in(array_map(
                static fn (EventStatus $status) => $status->value,
                EventStatus::cases(),
            ))],
            'module' => ['nullable', Rule::in(['live', 'wall', 'play', 'hub'])],
        ];
    }
}
