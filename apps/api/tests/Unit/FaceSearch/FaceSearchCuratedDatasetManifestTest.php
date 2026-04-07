<?php

it('defines the curated AI photo dataset manifest contract', function () {
    $root = base_path('tests/Fixtures/AI');
    $manifestPath = "{$root}/manifest.json";

    expect(is_file($manifestPath))->toBeTrue()
        ->and(is_file("{$root}/README.md"))->toBeTrue();

    $manifest = json_decode(
        (string) file_get_contents($manifestPath),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($manifest['status'] ?? null)->toBe('contract_ready_assets_pending')
        ->and($manifest['privacy']['raw_customer_uploads_allowed'] ?? null)->toBeFalse()
        ->and($manifest['privacy']['requires_anonymized_or_consented_assets'] ?? null)->toBeTrue()
        ->and($manifest['privacy']['secrets_allowed'] ?? null)->toBeFalse();

    $requiredGroups = [
        'safety-safe',
        'safety-review-block',
        'face-search-positive',
        'face-search-negative',
        'face-search-low-quality',
        'cross-event-isolation',
    ];

    $groups = collect($manifest['groups'] ?? [])->keyBy('key');

    foreach ($requiredGroups as $groupKey) {
        expect($groups->has($groupKey))->toBeTrue("Missing fixture group [{$groupKey}].");

        $group = $groups->get($groupKey);
        $directory = (string) ($group['directory'] ?? '');

        expect($directory)->not->toBe('')
            ->and(is_dir("{$root}/{$directory}"))->toBeTrue("Missing fixture directory [{$directory}].")
            ->and($group['entries'] ?? null)->toBeArray()
            ->and((int) ($group['min_entries_expected'] ?? 0))->toBeGreaterThan(0);

        assertAiFixtureEntriesFollowManifestContract(
            root: $root,
            groupKey: $groupKey,
            entries: $group['entries'],
            requiredFields: $manifest['required_fixture_fields'],
            allowedExtensions: $manifest['allowed_asset_extensions'],
        );
    }
});

/**
 * @param array<int, array<string, mixed>> $entries
 * @param array<int, string> $requiredFields
 * @param array<int, string> $allowedExtensions
 */
function assertAiFixtureEntriesFollowManifestContract(
    string $root,
    string $groupKey,
    array $entries,
    array $requiredFields,
    array $allowedExtensions,
): void {
    foreach ($entries as $entry) {
        foreach ($requiredFields as $field) {
            expect(array_key_exists($field, $entry))->toBeTrue("Missing field [{$field}] in fixture group [{$groupKey}].");
        }

        $path = (string) $entry['path'];
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        expect($entry['expected_positive_set'])->toBeArray()
            ->and($entry['is_public_search_eligible'])->toBeBool()
            ->and(in_array($extension, $allowedExtensions, true))->toBeTrue("Unsupported fixture extension [{$extension}] in [{$path}].")
            ->and(is_file("{$root}/{$path}"))->toBeTrue("Fixture file [{$path}] does not exist.");
    }
}
