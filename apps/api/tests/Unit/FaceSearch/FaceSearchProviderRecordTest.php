<?php

use App\Modules\FaceSearch\Models\FaceSearchProviderRecord;

it('persists provider records with deterministic external image ids and json payloads', function () {
    $record = \Database\Factories\FaceSearchProviderRecordFactory::new()->create([
        'external_image_id' => 'evt:42:media:99:rev:abc123',
        'unindexed_reasons_json' => ['LOW_SHARPNESS'],
        'provider_payload_json' => ['FaceId' => 'face-123'],
    ]);

    $record->refresh();

    expect($record)->toBeInstanceOf(FaceSearchProviderRecord::class)
        ->and($record->external_image_id)->toBe('evt:42:media:99:rev:abc123')
        ->and($record->unindexed_reasons_json)->toBe(['LOW_SHARPNESS'])
        ->and($record->provider_payload_json)->toBe(['FaceId' => 'face-123']);
});
