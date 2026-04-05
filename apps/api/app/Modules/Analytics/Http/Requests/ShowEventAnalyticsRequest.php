<?php

namespace App\Modules\Analytics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowEventAnalyticsRequest extends FormRequest
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
            'module' => ['nullable', Rule::in(['live', 'wall', 'play', 'hub'])],
        ];
    }
}
