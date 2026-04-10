<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->can('billing.manage') || $user?->can('billing.manage_subscription'));
    }

    public function rules(): array
    {
        return [
            'card_id' => ['nullable', 'string', 'required_without:card_token'],
            'card_token' => ['nullable', 'string', 'required_without:card_id'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.street' => ['nullable', 'string', 'max:120'],
            'billing_address.number' => ['nullable', 'string', 'max:40'],
            'billing_address.district' => ['nullable', 'string', 'max:120'],
            'billing_address.complement' => ['nullable', 'string', 'max:120'],
            'billing_address.zip_code' => ['nullable', 'string', 'max:20'],
            'billing_address.city' => ['nullable', 'string', 'max:120'],
            'billing_address.state' => ['nullable', 'string', 'size:2'],
            'billing_address.country' => ['nullable', 'string', 'size:2'],
        ];
    }
}
