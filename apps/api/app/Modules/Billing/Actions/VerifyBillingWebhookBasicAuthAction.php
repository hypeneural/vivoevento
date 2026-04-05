<?php

namespace App\Modules\Billing\Actions;

class VerifyBillingWebhookBasicAuthAction
{
    public function execute(string $provider, ?string $username, ?string $password, array $headers = [], array $server = []): bool
    {
        $expected = match ($provider) {
            'pagarme' => [
                'username' => config('services.pagarme.webhook_basic_auth_user'),
                'password' => config('services.pagarme.webhook_basic_auth_password'),
            ],
            default => null,
        };

        if (! is_array($expected)) {
            return true;
        }

        $expectedUsername = (string) ($expected['username'] ?? '');
        $expectedPassword = (string) ($expected['password'] ?? '');

        if ($expectedUsername === '' && $expectedPassword === '') {
            return true;
        }

        [$resolvedUsername, $resolvedPassword] = $this->resolveCredentials($username, $password, $headers, $server);

        return hash_equals($expectedUsername, $resolvedUsername)
            && hash_equals($expectedPassword, $resolvedPassword);
    }

    private function resolveCredentials(?string $username, ?string $password, array $headers, array $server): array
    {
        $resolvedUsername = trim((string) $username);
        $resolvedPassword = trim((string) $password);

        if ($resolvedUsername !== '' || $resolvedPassword !== '') {
            return [$resolvedUsername, $resolvedPassword];
        }

        $authorizationHeader = $this->firstHeaderValue($headers, 'authorization')
            ?? $this->firstServerAuthorizationValue($server);

        if (! is_string($authorizationHeader) || $authorizationHeader === '') {
            return ['', ''];
        }

        if (! str_starts_with(strtolower($authorizationHeader), 'basic ')) {
            return ['', ''];
        }

        $decoded = base64_decode(substr($authorizationHeader, 6), true);

        if (! is_string($decoded) || ! str_contains($decoded, ':')) {
            return ['', ''];
        }

        [$resolvedUsername, $resolvedPassword] = explode(':', $decoded, 2);

        return [$resolvedUsername, $resolvedPassword];
    }

    private function firstHeaderValue(array $headers, string $headerName): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== strtolower($headerName)) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            return is_string($value) ? trim($value) : null;
        }

        return null;
    }

    private function firstServerAuthorizationValue(array $server): ?string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $value = $server[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
