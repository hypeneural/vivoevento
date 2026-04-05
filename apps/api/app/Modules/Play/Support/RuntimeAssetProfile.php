<?php

namespace App\Modules\Play\Support;

final readonly class RuntimeAssetProfile
{
    public function __construct(
        public ?string $platform = null,
        public ?int $viewportWidth = null,
        public ?int $viewportHeight = null,
        public ?float $pixelRatio = null,
        public bool $saveData = false,
        public ?string $effectiveType = null,
        public ?float $downlink = null,
    ) {}

    public static function fromDevice(?array $device): ?self
    {
        if (! is_array($device) || $device === []) {
            return null;
        }

        $connection = is_array($device['connection'] ?? null) ? $device['connection'] : [];

        return new self(
            platform: self::nullableString($device['platform'] ?? null),
            viewportWidth: self::nullableInt($device['viewportWidth'] ?? $device['viewport_width'] ?? null),
            viewportHeight: self::nullableInt($device['viewportHeight'] ?? $device['viewport_height'] ?? null),
            pixelRatio: self::nullableFloat($device['pixelRatio'] ?? $device['pixel_ratio'] ?? null),
            saveData: self::nullableBool($connection['saveData'] ?? $connection['save_data'] ?? null) ?? false,
            effectiveType: self::nullableString($connection['effectiveType'] ?? $connection['effective_type'] ?? null),
            downlink: self::nullableFloat($connection['downlink'] ?? null),
        );
    }

    public static function fromQuery(array $query): ?self
    {
        if ($query === []) {
            return null;
        }

        return new self(
            platform: self::nullableString($query['platform'] ?? null),
            viewportWidth: self::nullableInt($query['viewport_width'] ?? null),
            viewportHeight: self::nullableInt($query['viewport_height'] ?? null),
            pixelRatio: self::nullableFloat($query['pixel_ratio'] ?? null),
            saveData: self::nullableBool($query['save_data'] ?? null) ?? false,
            effectiveType: self::nullableString($query['effective_type'] ?? null),
            downlink: self::nullableFloat($query['downlink'] ?? null),
        );
    }

    public function bucket(): string
    {
        $effectiveType = strtolower($this->effectiveType ?? '');

        if ($this->saveData || in_array($effectiveType, ['slow-2g', '2g'], true) || (($this->downlink ?? 10) < 1)) {
            return 'constrained';
        }

        if (
            $effectiveType === '3g'
            || (($this->downlink ?? 10) < 2.5)
            || (($this->viewportWidth ?? 9999) < 430)
        ) {
            return 'standard';
        }

        return 'rich';
    }

    /**
     * @return array<int, string>
     */
    public function preferredVariantKeys(?string $gameKey): array
    {
        return match ($gameKey) {
            'puzzle' => match ($this->bucket()) {
                'constrained' => ['gallery', 'wall', 'fast_preview', 'thumb'],
                'standard' => ['gallery', 'wall', 'fast_preview', 'thumb'],
                default => ['wall', 'gallery', 'fast_preview', 'thumb'],
            },
            'memory' => match ($this->bucket()) {
                'constrained' => ['fast_preview', 'gallery', 'thumb', 'wall'],
                'standard' => ['gallery', 'fast_preview', 'thumb', 'wall'],
                default => ['gallery', 'wall', 'fast_preview', 'thumb'],
            },
            default => ['gallery', 'fast_preview', 'wall', 'thumb'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = array_filter([
            'platform' => $this->platform,
            'viewportWidth' => $this->viewportWidth,
            'viewportHeight' => $this->viewportHeight,
            'pixelRatio' => $this->pixelRatio,
            'connection' => array_filter([
                'saveData' => $this->saveData,
                'effectiveType' => $this->effectiveType,
                'downlink' => $this->downlink,
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null && $value !== []);

        return $payload;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private static function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
