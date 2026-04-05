<?php

use App\Modules\Billing\Services\Pagarme\PagarmeCustomerNormalizer;

it('normalizes an individual payer into the pagarme customer shape', function () {
    $customer = app(PagarmeCustomerNormalizer::class)->normalize([
        'name' => 'Mariana Alves',
        'email' => 'mariana@example.com',
        'document' => '123.456.789-09',
        'document_type' => 'CPF',
        'phone' => '+55 (48) 99988-1111',
        'address' => [
            'street' => 'Rua Exemplo',
            'number' => '123',
            'district' => 'Centro',
            'complement' => 'Sala 2',
            'zip_code' => '88000-000',
            'city' => 'Florianopolis',
            'state' => 'SC',
            'country' => 'BR',
        ],
    ]);

    expect($customer)->toMatchArray([
        'name' => 'Mariana Alves',
        'email' => 'mariana@example.com',
        'type' => 'individual',
        'document_type' => 'CPF',
        'document' => '12345678909',
        'phones' => [
            'mobile_phone' => [
                'country_code' => '55',
                'area_code' => '48',
                'number' => '999881111',
            ],
        ],
        'address' => [
            'line_1' => 'Rua Exemplo, 123, Centro',
            'line_2' => 'Sala 2',
            'zip_code' => '88000000',
            'city' => 'Florianopolis',
            'state' => 'SC',
            'country' => 'BR',
        ],
    ]);
});

it('normalizes a company payer by inferring the customer type from the document', function () {
    $customer = app(PagarmeCustomerNormalizer::class)->normalize([
        'name' => 'Empresa Exemplo LTDA',
        'email' => 'financeiro@example.com',
        'document' => '12.345.678/0001-90',
        'phone' => '5548999881111',
        'address' => [
            'street' => 'Avenida Central',
            'number' => '500',
            'district' => 'Centro',
            'zip_code' => '88010000',
            'city' => 'Florianopolis',
            'state' => 'SC',
            'country' => 'BR',
        ],
    ]);

    expect($customer['type'])->toBe('company')
        ->and($customer['document'])->toBe('12345678000190');
});
