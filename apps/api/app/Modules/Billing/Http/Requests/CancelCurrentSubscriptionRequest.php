<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelCurrentSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) (
            $this->user()?->can('billing.manage')
            || $this->user()?->can('billing.manage_subscription')
        );
    }

    public function rules(): array
    {
        return [
            'effective' => ['nullable', 'string', 'in:period_end,immediately'],
            'reason' => ['nullable', 'string', 'max:180'],
        ];
    }
}
