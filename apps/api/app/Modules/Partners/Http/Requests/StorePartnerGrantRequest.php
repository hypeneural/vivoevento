<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('managePartnerGrants', $partner);
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'source_type' => ['required', Rule::in(array_map(static fn (EventAccessGrantSourceType $type) => $type->value, EventAccessGrantSourceType::cases()))],
            'reason' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
