<?php

it('validates the optional local consented dataset manifest when the asset root is available', function () {
    $manifestPath = base_path('tests/Fixtures/AI/local/vipsocial.manifest.json');

    expect(is_file($manifestPath))->toBeTrue();

    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest['status'] ?? null)->toBe('local_consented_assets_ready')
        ->and($manifest['privacy']['requires_explicit_consent'] ?? null)->toBeTrue()
        ->and($manifest['provider_constraints']['compreface_max_file_size_bytes'] ?? null)->toBe(5 * 1024 * 1024);

    $entries = collect($manifest['entries'] ?? []);

    expect($entries)->toHaveCount(7);

    $grouped = $entries->groupBy('person_id');

    expect($grouped->get('person-a', collect()))->toHaveCount(4)
        ->and($grouped->get('person-b', collect()))->toHaveCount(3);

    $assetRoot = resolveLocalAiDatasetRoot($manifest);

    if (! is_dir($assetRoot)) {
        return;
    }

    foreach ($entries as $entry) {
        $relativePath = (string) ($entry['relative_path'] ?? '');
        $absolutePath = $assetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $smokeRelativePath = (string) ($entry['smoke_relative_path'] ?? '');
        $smokeAbsolutePath = $smokeRelativePath !== ''
            ? $assetRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $smokeRelativePath)
            : null;
        $sizeBytes = (int) ($entry['size_bytes'] ?? 0);
        $smokeSizeBytes = (int) ($entry['smoke_size_bytes'] ?? 0);
        $requiresDerivative = (bool) ($entry['requires_derivative_for_compreface'] ?? false);
        $maxFileSize = (int) ($manifest['provider_constraints']['compreface_max_file_size_bytes'] ?? 0);
        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));

        expect($entry['expected_positive_set'] ?? null)->toBeArray()
            ->and($entry['scene_type'] ?? null)->toBeString()
            ->and($entry['quality_label'] ?? null)->toBeString()
            ->and($entry['is_public_search_eligible'] ?? null)->toBeBool()
            ->and($entry['expected_moderation_bucket'] ?? null)->toBe('safe')
            ->and($entry['consent_basis'] ?? null)->toBe('explicit_local_validation')
            ->and(in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true))->toBeTrue()
            ->and(is_file($absolutePath))->toBeTrue("Local fixture file [{$absolutePath}] does not exist.")
            ->and(filesize($absolutePath))->toBe($sizeBytes);

        if ($sizeBytes > $maxFileSize) {
            expect($requiresDerivative)->toBeTrue("Fixture [{$relativePath}] exceeds CompreFace size limit and must be flagged for derivative generation.");
        }

        if ($smokeRelativePath !== '') {
            expect($requiresDerivative)->toBeTrue()
                ->and($smokeAbsolutePath)->not->toBeNull()
                ->and(is_file((string) $smokeAbsolutePath))->toBeTrue("Smoke derivative [{$smokeRelativePath}] does not exist.")
                ->and(filesize((string) $smokeAbsolutePath))->toBe($smokeSizeBytes)
                ->and($smokeSizeBytes)->toBeLessThanOrEqual($maxFileSize);
        }

        $targetFaceSelection = $entry['target_face_selection'] ?? null;

        if (is_array($targetFaceSelection)) {
            expect($targetFaceSelection['strategy'] ?? null)->toBeString()
                ->and($targetFaceSelection['value'] ?? null)->toBeInt();
        }
    }
});

/**
 * @param array<string, mixed> $manifest
 */
function resolveLocalAiDatasetRoot(array $manifest): string
{
    $envKey = (string) ($manifest['asset_root_env'] ?? '');

    if ($envKey !== '') {
        $fromEnv = env($envKey);

        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return rtrim($fromEnv, "\\/");
        }
    }

    $fallback = (string) ($manifest['fallback_asset_root'] ?? '');

    if ($fallback === '') {
        return '';
    }

    $userProfile = getenv('USERPROFILE') ?: '';

    return rtrim(str_replace('%USERPROFILE%', (string) $userProfile, $fallback), "\\/");
}
