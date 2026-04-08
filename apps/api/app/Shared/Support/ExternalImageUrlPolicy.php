<?php

namespace App\Shared\Support;

class ExternalImageUrlPolicy
{
    public function isProviderReachable(?string $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = trim(strtolower((string) ($parts['host'] ?? '')), '[]');

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true)) {
            return false;
        }

        if (str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $isPublicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;

            if (! $isPublicIp) {
                return false;
            }
        }

        return true;
    }
}
