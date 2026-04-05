<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Events\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicEventCheckoutRequest extends FormRequest
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

        $payment = (array) $this->input('payment', []);
        $paymentMethod = $payment['method'] ?? null;

        if (! is_string($paymentMethod) || trim($paymentMethod) === '') {
            $paymentMethod = ! empty($payment['credit_card']) ? 'credit_card' : 'pix';
        }

        if ($paymentMethod === 'pix') {
            $pix = (array) ($payment['pix'] ?? []);
            $pix['expires_in'] = $pix['expires_in'] ?? (int) config('services.pagarme.pix_expires_in', 1800);
            $payment['pix'] = $pix;
        }

        $payment['method'] = $paymentMethod;

        $this->merge([
            'payment' => $payment,
        ]);
    }

    public function rules(): array
    {
        return [
            'responsible_name' => ['required', 'string', 'max:160'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'device_name' => ['nullable', 'string', 'max:80'],
            'package_id' => ['required', 'integer', 'exists:event_packages,id'],

            'event' => ['required', 'array'],
            'event.title' => ['required', 'string', 'max:180'],
            'event.event_type' => ['required', Rule::enum(EventType::class)],
            'event.event_date' => ['nullable', 'date'],
            'event.city' => ['nullable', 'string', 'max:180'],
            'event.description' => ['nullable', 'string', 'max:2000'],

            'payer' => ['nullable', 'array'],
            'payer.name' => ['nullable', 'string', 'max:160'],
            'payer.email' => ['nullable', 'email', 'max:160'],
            'payer.document' => ['nullable', 'string', 'max:30'],
            'payer.document_type' => ['nullable', 'string', 'max:20'],
            'payer.phone' => ['nullable', 'string', 'max:30'],
            'payer.address' => ['nullable', 'array'],
            'payer.address.street' => ['nullable', 'string', 'max:160'],
            'payer.address.number' => ['nullable', 'string', 'max:40'],
            'payer.address.district' => ['nullable', 'string', 'max:120'],
            'payer.address.complement' => ['nullable', 'string', 'max:160'],
            'payer.address.zip_code' => ['nullable', 'string', 'max:20'],
            'payer.address.city' => ['nullable', 'string', 'max:120'],
            'payer.address.state' => ['nullable', 'string', 'max:10'],
            'payer.address.country' => ['nullable', 'string', 'size:2'],

            'payment' => ['required', 'array'],
            'payment.method' => ['required', 'string', Rule::in(['pix', 'credit_card'])],
            'payment.pix' => ['nullable', 'array'],
            'payment.pix.expires_in' => ['nullable', 'integer', 'min:60', 'max:86400'],

            'payment.credit_card' => ['nullable', 'array'],
            'payment.credit_card.installments' => ['nullable', 'integer', 'min:1', 'max:24'],
            'payment.credit_card.statement_descriptor' => ['nullable', 'string', 'max:50'],
            'payment.credit_card.card_token' => ['nullable', 'string', 'max:120'],
            'payment.credit_card.billing_address' => ['nullable', 'array'],
            'payment.credit_card.billing_address.street' => ['nullable', 'string', 'max:160'],
            'payment.credit_card.billing_address.number' => ['nullable', 'string', 'max:40'],
            'payment.credit_card.billing_address.district' => ['nullable', 'string', 'max:120'],
            'payment.credit_card.billing_address.complement' => ['nullable', 'string', 'max:160'],
            'payment.credit_card.billing_address.zip_code' => ['nullable', 'string', 'max:20'],
            'payment.credit_card.billing_address.city' => ['nullable', 'string', 'max:120'],
            'payment.credit_card.billing_address.state' => ['nullable', 'string', 'max:10'],
            'payment.credit_card.billing_address.country' => ['nullable', 'string', 'size:2'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $method = $this->input('payment.method');

                if ($method === 'credit_card') {
                    $hasPayer = is_array($this->input('payer'));

                    if (! $hasPayer) {
                        $validator->errors()->add('payer', 'Informe os dados completos do pagador para checkout com cartao.');
                    }

                    foreach ([
                        'payer.name',
                        'payer.email',
                        'payer.document',
                        'payer.phone',
                        'payer.address.street',
                        'payer.address.number',
                        'payer.address.district',
                        'payer.address.zip_code',
                        'payer.address.city',
                        'payer.address.state',
                        'payer.address.country',
                        'payment.credit_card.installments',
                        'payment.credit_card.card_token',
                    ] as $field) {
                        if (str_starts_with($field, 'payer.') && ! $hasPayer) {
                            continue;
                        }

                        if (blank($this->input($field))) {
                            $validator->errors()->add($field, 'Este campo e obrigatorio para checkout com cartao.');
                        }
                    }

                    $billingAddressFields = [
                        'payment.credit_card.billing_address.street',
                        'payment.credit_card.billing_address.number',
                        'payment.credit_card.billing_address.district',
                        'payment.credit_card.billing_address.zip_code',
                        'payment.credit_card.billing_address.city',
                        'payment.credit_card.billing_address.state',
                        'payment.credit_card.billing_address.country',
                    ];

                    $hasAnyBillingAddressField = collect($billingAddressFields)
                        ->contains(fn (string $field): bool => filled($this->input($field)));

                    if ($hasAnyBillingAddressField) {
                        foreach ($billingAddressFields as $field) {
                            if (blank($this->input($field))) {
                                $validator->errors()->add($field, 'Complete o billing address do cartao.');
                            }
                        }
                    }
                }

                if ($method === 'pix' && filled($this->input('payment.credit_card.card_token'))) {
                    $validator->errors()->add('payment.credit_card.card_token', 'Nao envie card_token em checkout Pix.');
                }
            },
        ];
    }
}
