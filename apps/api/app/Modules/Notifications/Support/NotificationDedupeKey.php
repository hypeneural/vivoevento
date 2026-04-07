<?php

namespace App\Modules\Notifications\Support;

use InvalidArgumentException;

final class NotificationDedupeKey
{
    public static function make(string $code, string $scope, int|string $id): string
    {
        $code = self::normalize($code, 'code');
        $scope = self::normalize($scope, 'scope');
        $id = trim((string) $id);

        if ($id === '' || str_contains($id, ':')) {
            throw new InvalidArgumentException('Notification dedupe id must be a non-empty value without separators.');
        }

        return "{$code}:{$scope}:{$id}";
    }

    private static function normalize(string $value, string $field): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', '-', $value) ?? '';

        if ($value === '' || preg_match('/[^a-z0-9._-]/', $value) === 1) {
            throw new InvalidArgumentException("Notification dedupe {$field} must contain only letters, numbers, dots, underscores or dashes.");
        }

        return $value;
    }
}
