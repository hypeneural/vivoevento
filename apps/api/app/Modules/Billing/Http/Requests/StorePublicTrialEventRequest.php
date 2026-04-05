<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Events\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicTrialEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('whatsapp') && $this->filled('phone')) {
            $this->merge([
                'whatsapp' => $this->input('phone'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'responsible_name' => ['required', 'string', 'max:160'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'device_name' => ['nullable', 'string', 'max:80'],

            'event' => ['required', 'array'],
            'event.title' => ['required', 'string', 'max:180'],
            'event.event_type' => ['required', Rule::enum(EventType::class)],
            'event.event_date' => ['nullable', 'date'],
            'event.city' => ['nullable', 'string', 'max:180'],
            'event.description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
