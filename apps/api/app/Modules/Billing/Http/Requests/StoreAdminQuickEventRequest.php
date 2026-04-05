<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EntitlementMergeStrategy;
use App\Modules\Events\Enums\EventType;
use App\Modules\Organizations\Enums\OrganizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminQuickEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->hasAnyRole(['super-admin', 'platform-admin']) || $user->can('partners.manage'));
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
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'organization_type' => [
                'nullable',
                Rule::enum(OrganizationType::class),
                Rule::in([OrganizationType::Partner->value, OrganizationType::DirectCustomer->value]),
            ],
            'send_access' => ['nullable', 'boolean'],

            'event' => ['required', 'array'],
            'event.title' => ['required', 'string', 'max:180'],
            'event.event_type' => ['required', Rule::enum(EventType::class)],
            'event.event_date' => ['nullable', 'date'],
            'event.city' => ['nullable', 'string', 'max:180'],
            'event.description' => ['nullable', 'string', 'max:2000'],
            'event.visibility' => ['nullable', 'string', Rule::in(['public', 'private'])],
            'event.moderation_mode' => ['nullable', 'string', Rule::in(['none', 'manual', 'ai'])],

            'grant' => ['required', 'array'],
            'grant.source_type' => [
                'required',
                Rule::enum(EventAccessGrantSourceType::class),
                Rule::in([
                    EventAccessGrantSourceType::Bonus->value,
                    EventAccessGrantSourceType::ManualOverride->value,
                ]),
            ],
            'grant.package_id' => ['required', 'integer', 'exists:event_packages,id'],
            'grant.merge_strategy' => ['nullable', Rule::enum(EntitlementMergeStrategy::class)],
            'grant.starts_at' => ['nullable', 'date'],
            'grant.ends_at' => ['nullable', 'date', 'after_or_equal:grant.starts_at'],
            'grant.reason' => ['required', 'string', 'max:160'],
            'grant.origin' => ['nullable', 'string', 'max:120'],
            'grant.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
