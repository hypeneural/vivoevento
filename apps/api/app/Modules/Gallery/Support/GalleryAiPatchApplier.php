<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Support;

use App\Modules\Events\Models\Event;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class GalleryAiPatchApplier
{
    private const TARGET_LAYERS = ['mixed', 'theme_tokens', 'page_schema', 'media_behavior'];

    private const MATRIX_KEYS = [
        'event_type_family',
        'style_skin',
        'behavior_profile',
        'theme_key',
        'layout_key',
    ];

    private const PATCH_KEYS = [
        'theme_tokens',
        'page_schema',
        'media_behavior',
    ];

    public function __construct(
        private readonly GalleryBuilderPresetRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $currentPayload
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $matrixOverrides
     * @return array{
     *   scope:string,
     *   available_layers:array<int, string>,
     *   model_matrix:array<string, string>,
     *   patch:array<string, mixed>,
     *   normalized_payload:array<string, mixed>
     * }
     */
    public function applyPatch(
        Event $event,
        array $currentPayload,
        array $patch,
        array $matrixOverrides = [],
        string $targetLayer = 'mixed',
    ): array {
        $targetLayer = in_array($targetLayer, self::TARGET_LAYERS, true) ? $targetLayer : 'mixed';

        $this->guardKeys($patch, self::PATCH_KEYS, 'patch');
        $this->guardKeys($matrixOverrides, self::MATRIX_KEYS, 'model_matrix');
        $this->guardNoFreeformMarkup($patch, 'patch');
        $this->guardNoFreeformMarkup($matrixOverrides, 'model_matrix');

        $merged = $currentPayload;

        foreach (self::MATRIX_KEYS as $key) {
            if (array_key_exists($key, $matrixOverrides)) {
                $merged[$key] = $matrixOverrides[$key];
            }
        }

        foreach (self::PATCH_KEYS as $layer) {
            if (! array_key_exists($layer, $patch)) {
                continue;
            }

            $merged[$layer] = $this->recursiveMerge(
                is_array($merged[$layer] ?? null) ? $merged[$layer] : [],
                is_array($patch[$layer]) ? $patch[$layer] : [],
            );
        }

        $normalized = $this->registry->normalize($event, $merged);
        $this->registry->assertAccessible($normalized['theme_tokens']);

        $diffs = [];

        foreach (self::PATCH_KEYS as $layer) {
            $diff = $this->recursiveDiff(
                is_array($currentPayload[$layer] ?? null) ? $currentPayload[$layer] : [],
                is_array($normalized[$layer] ?? null) ? $normalized[$layer] : [],
            );

            if ($diff !== null) {
                $diffs[$layer] = $diff;
            }
        }

        $availableLayers = array_keys($diffs);

        if ($targetLayer !== 'mixed') {
            $diffs = array_key_exists($targetLayer, $diffs)
                ? [$targetLayer => $diffs[$targetLayer]]
                : [];
        }

        return [
            'scope' => $targetLayer,
            'available_layers' => $availableLayers,
            'model_matrix' => Arr::only($normalized, self::MATRIX_KEYS),
            'patch' => $diffs,
            'normalized_payload' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowedKeys
     */
    private function guardKeys(array $payload, array $allowedKeys, string $root): void
    {
        $unexpected = array_values(array_diff(array_keys($payload), $allowedKeys));

        if ($unexpected === []) {
            return;
        }

        throw ValidationException::withMessages([
            $root => sprintf(
                'A IA tentou alterar campos fora do catalogo permitido: %s.',
                implode(', ', $unexpected),
            ),
        ]);
    }

    private function guardNoFreeformMarkup(mixed $value, string $path): void
    {
        if (is_string($value)) {
            if (preg_match('/<[^>]+>|<\/|className\s*=|style\s*=|position\s*:|display\s*:|function\s*\(|=>/iu', $value)) {
                throw ValidationException::withMessages([
                    $path => 'A IA tentou devolver HTML, CSS, JSX ou codigo fora do catalogo permitido.',
                ]);
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $this->guardNoFreeformMarkup($item, sprintf('%s.%s', $path, (string) $key));
        }
    }

    private function recursiveMerge(mixed $base, mixed $override): mixed
    {
        if (! is_array($base) || ! is_array($override)) {
            return $override;
        }

        if (! Arr::isAssoc($base) || ! Arr::isAssoc($override)) {
            return $override;
        }

        $merged = $base;

        foreach ($override as $key => $value) {
            $merged[$key] = array_key_exists($key, $merged)
                ? $this->recursiveMerge($merged[$key], $value)
                : $value;
        }

        return $merged;
    }

    private function recursiveDiff(mixed $original, mixed $updated): mixed
    {
        if (! is_array($original) || ! is_array($updated)) {
            return $original !== $updated ? $updated : null;
        }

        if (! Arr::isAssoc($original) || ! Arr::isAssoc($updated)) {
            return $original !== $updated ? $updated : null;
        }

        $diff = [];

        foreach ($updated as $key => $value) {
            $nested = $this->recursiveDiff($original[$key] ?? null, $value);

            if ($nested !== null) {
                $diff[$key] = $nested;
            }
        }

        return $diff === [] ? null : $diff;
    }
}
