<?php

namespace App\Modules\Telegram\Support;

class TelegramPrivateCommandParser
{
    public function extractActivationCode(?string $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', trim($text));

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        if (! preg_match('/^\/start\s+(.+)$/iu', $normalized, $matches)) {
            return null;
        }

        $code = strtoupper(trim((string) ($matches[1] ?? '')));

        return $code !== '' ? $code : null;
    }

    public function extractStandaloneActivationCode(?string $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $normalized = trim($text);

        if ($normalized === '' || str_starts_with($normalized, '/')) {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{3,64}$/', $normalized)) {
            return null;
        }

        return strtoupper($normalized);
    }

    public function isExitCommand(?string $text): bool
    {
        if (! is_string($text)) {
            return false;
        }

        $normalized = strtolower(trim($text));

        return in_array($normalized, ['sair', '/sair', '/stop'], true);
    }
}
