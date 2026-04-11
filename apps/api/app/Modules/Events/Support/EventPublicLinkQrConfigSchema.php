<?php

namespace App\Modules\Events\Support;

use InvalidArgumentException;

class EventPublicLinkQrConfigSchema
{
    public const CONFIG_VERSION = 'event-public-link-qr.v1';

    public const LINK_KEYS = ['gallery', 'upload', 'wall', 'hub', 'play', 'find_me'];
    public const USAGE_PRESETS = ['telao', 'upload_rapido', 'galeria_premium', 'impresso_pequeno', 'convite_whatsapp'];
    public const SKIN_PRESETS = ['classico', 'premium', 'minimalista', 'escuro'];
    public const LOGO_MODES = ['none', 'event_logo', 'organization_logo', 'custom'];
    public const DRAW_TYPES = ['svg', 'canvas'];
    public const BACKGROUND_MODES = ['solid', 'transparent'];
    public const EXPORT_EXTENSIONS = ['png', 'jpeg', 'webp', 'svg'];
    public const ERROR_CORRECTION_LEVELS = ['L', 'M', 'Q', 'H'];
    public const SHAPES = ['square', 'circle'];
    public const MODES = ['Numeric', 'Alphanumeric', 'Byte', 'Kanji'];

    public function assertValidLinkKey(string $linkKey): void
    {
        if (! in_array($linkKey, self::LINK_KEYS, true)) {
            throw new InvalidArgumentException("Unsupported QR link key [{$linkKey}].");
        }
    }

    public function defaultForLink(string $linkKey, ?array $effectiveBranding = null): array
    {
        return $this->normalize(
            input: [],
            linkKey: $linkKey,
            effectiveBranding: $effectiveBranding,
            applyBrandingDefaults: true,
        );
    }

    public function normalize(
        ?array $input,
        string $linkKey,
        ?array $effectiveBranding = null,
        bool $applyBrandingDefaults = false,
    ): array {
        $this->assertValidLinkKey($linkKey);

        $migrated = $this->migrate($input);
        $defaults = $this->buildDefaults(
            linkKey: $linkKey,
            usagePreset: $migrated['usage_preset'] ?? null,
            skinPreset: $migrated['skin_preset'] ?? null,
        );
        $brandingSeed = $applyBrandingDefaults
            ? $this->buildBrandingSeed($effectiveBranding ?? [])
            : [];

        return $this->applyGuardrails(
            $this->mergeConfig(
                $defaults,
                $brandingSeed,
                $migrated,
                ['config_version' => self::CONFIG_VERSION],
            ),
        );
    }

    public function migrate(?array $input): array
    {
        $migrated = $this->cloneArray($input ?? []);

        if (($migrated['version'] ?? null) && ! ($migrated['config_version'] ?? null)) {
            $migrated['config_version'] = self::CONFIG_VERSION;
        }

        unset($migrated['version']);

        if (
            isset($migrated['render'])
            && is_array($migrated['render'])
            && array_key_exists('margin', $migrated['render'])
            && ! array_key_exists('margin_modules', $migrated['render'])
        ) {
            $migrated['render']['margin_modules'] = $migrated['render']['margin'];
        }

        if (isset($migrated['render']) && is_array($migrated['render'])) {
            unset($migrated['render']['margin']);
        }

        if (
            isset($migrated['advanced'])
            && is_array($migrated['advanced'])
            && array_key_exists('error_correction', $migrated['advanced'])
            && ! array_key_exists('error_correction_level', $migrated['advanced'])
        ) {
            $migrated['advanced']['error_correction_level'] = $migrated['advanced']['error_correction'];
        }

        if (isset($migrated['advanced']) && is_array($migrated['advanced'])) {
            unset($migrated['advanced']['error_correction']);
        }

        if (! ($migrated['config_version'] ?? null)) {
            $migrated['config_version'] = self::CONFIG_VERSION;
        }

        return $migrated;
    }

    public function mergeConfig(array $base, array ...$patches): array
    {
        return array_reduce(
            $patches,
            fn (array $accumulator, array $patch) => $this->mergeRecords($accumulator, $patch),
            $this->cloneArray($base),
        );
    }

    public function applyGuardrails(array $config): array
    {
        $usagePreset = in_array($config['usage_preset'] ?? null, self::USAGE_PRESETS, true)
            ? $config['usage_preset']
            : 'galeria_premium';
        $skinPreset = in_array($config['skin_preset'] ?? null, self::SKIN_PRESETS, true)
            ? $config['skin_preset']
            : 'classico';
        $renderType = in_array($config['render']['preview_type'] ?? null, self::DRAW_TYPES, true)
            ? $config['render']['preview_type']
            : 'svg';
        $backgroundMode = in_array($config['render']['background_mode'] ?? null, self::BACKGROUND_MODES, true)
            ? $config['render']['background_mode']
            : 'solid';
        $logoMode = in_array($config['logo']['mode'] ?? null, self::LOGO_MODES, true)
            ? $config['logo']['mode']
            : 'none';
        $errorCorrectionLevel = in_array($config['advanced']['error_correction_level'] ?? null, self::ERROR_CORRECTION_LEVELS, true)
            ? $config['advanced']['error_correction_level']
            : 'Q';
        $shape = in_array($config['advanced']['shape'] ?? null, self::SHAPES, true)
            ? $config['advanced']['shape']
            : 'square';
        $mode = in_array($config['advanced']['mode'] ?? null, self::MODES, true)
            ? $config['advanced']['mode']
            : 'Byte';
        $exportExtension = in_array($config['export_defaults']['extension'] ?? null, self::EXPORT_EXTENSIONS, true)
            ? $config['export_defaults']['extension']
            : 'svg';
        $marginModules = $this->clampMarginModules($config['render']['margin_modules'] ?? null);
        $imageSize = $this->clampImageSize($config['logo']['image_size'] ?? null);
        $transparentBackground = (bool) (($config['style']['background']['transparent'] ?? false) || $backgroundMode === 'transparent');
        $hasLogo = $logoMode !== 'none';

        if ($transparentBackground && $exportExtension === 'jpeg') {
            $exportExtension = 'png';
        }

        if ($hasLogo) {
            $errorCorrectionLevel = 'H';
        }

        return [
            ...$config,
            'config_version' => self::CONFIG_VERSION,
            'usage_preset' => $usagePreset,
            'skin_preset' => $skinPreset,
            'render' => [
                ...($config['render'] ?? []),
                'preview_type' => $renderType,
                'preview_size' => $this->toInt($config['render']['preview_size'] ?? 320, 320),
                'margin_modules' => $marginModules,
                'background_mode' => $transparentBackground ? 'transparent' : 'solid',
            ],
            'style' => [
                ...($config['style'] ?? []),
                'background' => [
                    ...($config['style']['background'] ?? []),
                    'transparent' => $transparentBackground,
                ],
            ],
            'logo' => [
                ...($config['logo'] ?? []),
                'mode' => $logoMode,
                'image_size' => $imageSize,
                'margin_px' => $this->toInt($config['logo']['margin_px'] ?? 8, 8),
                'hide_background_dots' => (bool) ($config['logo']['hide_background_dots'] ?? true),
                'save_as_blob' => (bool) ($config['logo']['save_as_blob'] ?? true),
            ],
            'advanced' => [
                ...($config['advanced'] ?? []),
                'error_correction_level' => $errorCorrectionLevel,
                'shape' => $shape,
                'round_size' => (bool) ($config['advanced']['round_size'] ?? true),
                'type_number' => $this->clampTypeNumber($config['advanced']['type_number'] ?? 0),
                'mode' => $mode,
            ],
            'export_defaults' => [
                ...($config['export_defaults'] ?? []),
                'extension' => $exportExtension,
                'size' => $this->toInt($config['export_defaults']['size'] ?? 1024, 1024),
                'download_name_pattern' => (string) ($config['export_defaults']['download_name_pattern'] ?? 'evento-{event_id}-{link_key}'),
            ],
        ];
    }

    private function buildDefaults(string $linkKey, ?string $usagePreset = null, ?string $skinPreset = null): array
    {
        $usagePreset = in_array($usagePreset, self::USAGE_PRESETS, true)
            ? $usagePreset
            : $this->getDefaultUsagePresetForLinkKey($linkKey);
        $skinPreset = in_array($skinPreset, self::SKIN_PRESETS, true)
            ? $skinPreset
            : 'classico';

        return $this->mergeConfig(
            $this->buildBaseConfig(),
            [
                'usage_preset' => $usagePreset,
                'skin_preset' => $skinPreset,
            ],
            $this->usagePresetPatch($usagePreset),
            $this->skinPresetPatch($skinPreset),
        );
    }

    private function buildBaseConfig(): array
    {
        return [
            'config_version' => self::CONFIG_VERSION,
            'usage_preset' => 'galeria_premium',
            'skin_preset' => 'classico',
            'render' => [
                'preview_type' => 'svg',
                'preview_size' => 320,
                'margin_modules' => 4,
                'background_mode' => 'solid',
            ],
            'style' => [
                'dots' => [
                    'type' => 'rounded',
                    'color' => '#0f172a',
                    'gradient' => null,
                ],
                'corners_square' => [
                    'type' => 'extra-rounded',
                    'color' => '#0f172a',
                    'gradient' => null,
                ],
                'corners_dot' => [
                    'type' => 'dot',
                    'color' => '#0f172a',
                    'gradient' => null,
                ],
                'background' => [
                    'color' => '#ffffff',
                    'gradient' => null,
                    'transparent' => false,
                ],
            ],
            'logo' => [
                'mode' => 'none',
                'asset_path' => null,
                'asset_url' => null,
                'image_size' => 0.22,
                'margin_px' => 8,
                'hide_background_dots' => true,
                'save_as_blob' => true,
            ],
            'advanced' => [
                'error_correction_level' => 'Q',
                'shape' => 'square',
                'round_size' => true,
                'type_number' => 0,
                'mode' => 'Byte',
            ],
            'export_defaults' => [
                'extension' => 'svg',
                'size' => 1024,
                'download_name_pattern' => 'evento-{event_id}-{link_key}',
            ],
        ];
    }

    private function buildBrandingSeed(array $effectiveBranding): array
    {
        $hasVisualBranding = filled($effectiveBranding['logo_url'] ?? null)
            || filled($effectiveBranding['primary_color'] ?? null)
            || filled($effectiveBranding['secondary_color'] ?? null);

        return [
            'skin_preset' => $hasVisualBranding ? 'premium' : null,
            'style' => [
                'dots' => [
                    'color' => $effectiveBranding['primary_color'] ?? null,
                ],
                'corners_square' => [
                    'color' => $effectiveBranding['primary_color'] ?? null,
                ],
                'corners_dot' => [
                    'color' => $effectiveBranding['secondary_color'] ?? ($effectiveBranding['primary_color'] ?? null),
                ],
            ],
            'logo' => filled($effectiveBranding['logo_url'] ?? null) ? [
                'mode' => 'event_logo',
                'asset_url' => $effectiveBranding['logo_url'],
            ] : null,
        ];
    }

    private function usagePresetPatch(string $usagePreset): array
    {
        return match ($usagePreset) {
            'telao' => [
                'advanced' => ['error_correction_level' => 'H'],
                'export_defaults' => ['extension' => 'png', 'size' => 2048],
                'logo' => ['image_size' => 0.18],
            ],
            'upload_rapido' => [
                'export_defaults' => ['extension' => 'png', 'size' => 1024],
            ],
            'impresso_pequeno' => [
                'advanced' => ['error_correction_level' => 'H'],
                'logo' => ['image_size' => 0.18],
                'export_defaults' => ['extension' => 'png', 'size' => 2048],
            ],
            'convite_whatsapp' => [
                'export_defaults' => ['extension' => 'png', 'size' => 1024],
            ],
            default => [
                'export_defaults' => ['extension' => 'svg', 'size' => 1024],
            ],
        };
    }

    private function skinPresetPatch(string $skinPreset): array
    {
        return match ($skinPreset) {
            'premium' => [
                'style' => [
                    'dots' => ['type' => 'rounded'],
                    'corners_square' => ['type' => 'extra-rounded'],
                ],
            ],
            'minimalista' => [
                'style' => [
                    'dots' => ['type' => 'square'],
                    'corners_square' => ['type' => 'square'],
                    'corners_dot' => ['type' => 'square'],
                ],
            ],
            'escuro' => [
                'style' => [
                    'dots' => ['color' => '#020617'],
                    'corners_square' => ['color' => '#020617'],
                    'corners_dot' => ['color' => '#020617'],
                    'background' => ['color' => '#ffffff'],
                ],
            ],
            default => [],
        };
    }

    private function getDefaultUsagePresetForLinkKey(string $linkKey): string
    {
        return match ($linkKey) {
            'upload' => 'upload_rapido',
            'wall' => 'telao',
            'play', 'find_me' => 'convite_whatsapp',
            default => 'galeria_premium',
        };
    }

    private function mergeRecords(array $target, array $patch): array
    {
        $result = $target;

        foreach ($patch as $key => $value) {
            if ($value === null) {
                $result[$key] = null;
                continue;
            }

            if (
                isset($result[$key])
                && is_array($result[$key])
                && is_array($value)
                && ! array_is_list($result[$key])
                && ! array_is_list($value)
            ) {
                $result[$key] = $this->mergeRecords($result[$key], $value);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function cloneArray(array $value): array
    {
        return json_decode((string) json_encode($value), true) ?? [];
    }

    private function clampMarginModules(mixed $value): int
    {
        return max(4, $this->toInt($value, 4));
    }

    private function clampImageSize(mixed $value): float
    {
        $float = is_numeric($value) ? (float) $value : 0.22;

        return min(0.5, max(0.0, $float));
    }

    private function clampTypeNumber(mixed $value): int
    {
        return min(40, max(0, $this->toInt($value, 0)));
    }

    private function toInt(mixed $value, int $fallback): int
    {
        return is_numeric($value) ? (int) round((float) $value) : $fallback;
    }
}
