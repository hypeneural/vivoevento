<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user?->can('billing.manage') || $user?->can('billing.purchase'));
    }

    public function rules(): array
    {
        $rules = [
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
        ];

        if (config('billing.gateways.subscription') !== 'pagarme') {
            return $rules;
        }

        return array_merge($rules, [
            'payment_method' => ['required', 'string', 'in:credit_card'],
            'payer' => ['required', 'array'],
            'payer.name' => ['required', 'string', 'max:120'],
            'payer.email' => ['nullable', 'email', 'max:120'],
            'payer.document' => ['required', 'string', 'max:40'],
            'payer.phone' => ['required', 'string', 'max:40'],
            'payer.address' => ['required', 'array'],
            'payer.address.street' => ['required', 'string', 'max:120'],
            'payer.address.number' => ['required', 'string', 'max:40'],
            'payer.address.district' => ['required', 'string', 'max:120'],
            'payer.address.zip_code' => ['required', 'string', 'max:20'],
            'payer.address.city' => ['required', 'string', 'max:120'],
            'payer.address.state' => ['required', 'string', 'size:2'],
            'payer.address.country' => ['required', 'string', 'size:2'],
            'credit_card' => ['required', 'array'],
            'credit_card.card_token' => ['required_without:credit_card.card_id', 'string'],
            'credit_card.card_id' => ['nullable', 'string'],
            'credit_card.billing_address' => ['nullable', 'array'],
        ]);
    }
}
