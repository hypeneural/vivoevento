<?php

namespace App\Modules\Billing\Services\Pagarme;

class PagarmeCustomerNormalizer
{
    public function normalize(array $payer): array
    {
        $document = $this->digits((string) ($payer['document'] ?? ''));
        $documentType = $this->documentType($payer, $document);

        return array_filter([
            'name' => trim((string) ($payer['name'] ?? '')),
            'email' => filled($payer['email'] ?? null) ? trim((string) $payer['email']) : null,
            'type' => $documentType === 'CNPJ' ? 'company' : 'individual',
            'document_type' => $documentType,
            'document' => $document !== '' ? $document : null,
            'phones' => $this->normalizePhones((string) ($payer['phone'] ?? '')),
            'address' => $this->normalizeAddress((array) ($payer['address'] ?? [])),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    public function normalizeBillingAddress(array $address): array
    {
        return $this->normalizeAddress($address, includeLine2: false);
    }

    private function normalizePhones(string $phone): array
    {
        $digits = $this->digits($phone);

        if ($digits === '') {
            return [];
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55'.$digits;
        }

        $countryCode = substr($digits, 0, 2);
        $localDigits = substr($digits, 2);
        $areaCode = substr($localDigits, 0, 2);
        $number = substr($localDigits, 2);

        if ($countryCode === '' || $areaCode === '' || $number === '') {
            return [];
        }

        return [
            'mobile_phone' => [
                'country_code' => $countryCode,
                'area_code' => $areaCode,
                'number' => $number,
            ],
        ];
    }

    private function normalizeAddress(array $address, bool $includeLine2 = true): array
    {
        $line1Parts = array_filter([
            trim((string) ($address['street'] ?? '')),
            trim((string) ($address['number'] ?? '')),
            trim((string) ($address['district'] ?? '')),
        ], fn (string $value): bool => $value !== '');

        return array_filter([
            'line_1' => ! empty($line1Parts) ? implode(', ', $line1Parts) : null,
            'line_2' => $includeLine2 && filled($address['complement'] ?? null)
                ? trim((string) $address['complement'])
                : null,
            'zip_code' => filled($address['zip_code'] ?? null) ? $this->digits((string) $address['zip_code']) : null,
            'city' => filled($address['city'] ?? null) ? trim((string) $address['city']) : null,
            'state' => filled($address['state'] ?? null) ? strtoupper(trim((string) $address['state'])) : null,
            'country' => filled($address['country'] ?? null) ? strtoupper(trim((string) $address['country'])) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function documentType(array $payer, string $document): string
    {
        $declared = strtoupper(trim((string) ($payer['document_type'] ?? '')));

        if (in_array($declared, ['CPF', 'CNPJ'], true)) {
            return $declared;
        }

        return strlen($document) > 11 ? 'CNPJ' : 'CPF';
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
