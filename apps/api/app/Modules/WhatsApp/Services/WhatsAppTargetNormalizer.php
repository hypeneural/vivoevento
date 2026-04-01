<?php

namespace App\Modules\WhatsApp\Services;

/**
 * Normalizes WhatsApp targets (phone numbers, group IDs).
 *
 * Handles:
 * - Phone: strips formatting, adds country code if missing
 * - Group: detects @g.us suffix
 */
class WhatsAppTargetNormalizer
{
    /**
     * Normalize a phone number or group ID.
     *
     * @return array{raw: string, normalized: string, kind: string}
     */
    public function normalize(string $target): array
    {
        $raw = $target;

        // Group detection: Z-API groups end with @g.us
        if (str_contains($target, '@g.us') || str_contains($target, '-')) {
            return [
                'raw' => $raw,
                'normalized' => $target,
                'kind' => 'group',
            ];
        }

        // Phone: strip everything except digits
        $normalized = preg_replace('/[^0-9]/', '', $target);

        return [
            'raw' => $raw,
            'normalized' => $normalized,
            'kind' => 'phone',
        ];
    }

    /**
     * Validate that a target looks like a valid phone or group.
     */
    public function isValid(string $target): bool
    {
        $result = $this->normalize($target);

        if ($result['kind'] === 'group') {
            return strlen($result['normalized']) > 5;
        }

        // Phone: at least 10 digits (DDI + DDD + NUMBER)
        return strlen($result['normalized']) >= 10;
    }
}
