<?php

namespace App\Modules\Partners\Http\Requests;

use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $partner = $this->route('partner');

        return $partner instanceof Organization
            && (bool) $this->user()?->can('updatePartner', $partner);
    }

    public function rules(): array
    {
        /** @var Organization|null $partner */
        $partner = $this->route('partner');

        return [
            'name' => ['sometimes', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('organizations', 'email')->ignore($partner?->id)],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'segment' => ['nullable', 'string', 'max:80'],
            'business_stage' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
