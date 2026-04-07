<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Billing\Enums\EventAccessGrantSourceType;
use App\Modules\Billing\Enums\EventAccessGrantStatus;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPartnerGrantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('viewPartner', $partner);
    }

    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'source_type' => ['nullable', Rule::in(array_map(static fn (EventAccessGrantSourceType $type) => $type->value, EventAccessGrantSourceType::cases()))],
            'status' => ['nullable', Rule::in(array_map(static fn (EventAccessGrantStatus $status) => $status->value, EventAccessGrantStatus::cases()))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
