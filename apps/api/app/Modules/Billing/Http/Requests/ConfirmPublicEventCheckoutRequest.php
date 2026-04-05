<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPublicEventCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway_provider' => ['nullable', 'string', 'max:40'],
            'gateway_order_id' => ['nullable', 'string', 'max:120'],
            'gateway_payment_id' => ['nullable', 'string', 'max:120'],
            'confirmed_at' => ['nullable', 'date'],
        ];
    }
}
