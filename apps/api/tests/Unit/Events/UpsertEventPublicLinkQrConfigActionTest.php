<?php

use App\Modules\Events\Actions\UpsertEventPublicLinkQrConfigAction;
use App\Modules\Events\Models\Event;
use App\Modules\Users\Models\User;

it('normalizes and stores the semantic QR config before persisting', function () {
    $event = Event::factory()->active()->create();
    $user = User::factory()->create();

    $record = app(UpsertEventPublicLinkQrConfigAction::class)->execute(
        event: $event,
        linkKey: 'upload',
        config: [
            'version' => 'legacy-v0',
            'usage_preset' => 'upload_rapido',
            'render' => [
                'margin' => 1,
            ],
            'logo' => [
                'mode' => 'custom',
                'asset_url' => 'https://cdn.example.com/logo.png',
                'image_size' => 0.9,
            ],
            'advanced' => [
                'error_correction' => 'Q',
            ],
        ],
        userId: $user->id,
    );

    expect($record->config_version)->toBe('event-public-link-qr.v1')
        ->and($record->config_json['config_version'])->toBe('event-public-link-qr.v1')
        ->and($record->config_json['render']['margin_modules'])->toBe(4)
        ->and(isset($record->config_json['render']['margin']))->toBeFalse()
        ->and($record->config_json['logo']['image_size'])->toBe(0.5)
        ->and($record->config_json['advanced']['error_correction_level'])->toBe('H')
        ->and(isset($record->config_json['advanced']['error_correction']))->toBeFalse()
        ->and($record->updated_by)->toBe($user->id);
});

it('accepts the new stylized skin presets and persists their gradient defaults', function () {
    $event = Event::factory()->active()->create();
    $user = User::factory()->create();

    $record = app(UpsertEventPublicLinkQrConfigAction::class)->execute(
        event: $event,
        linkKey: 'gallery',
        config: [
            'skin_preset' => 'oceano',
        ],
        userId: $user->id,
    );

    expect($record->config_json['skin_preset'])->toBe('oceano')
        ->and($record->config_json['style']['dots']['gradient']['type'])->toBe('linear')
        ->and($record->config_json['style']['background']['color'])->toBe('#f8fafc');
});

it('accepts the extended preset catalog with additional stylized skins', function () {
    $event = Event::factory()->active()->create();
    $user = User::factory()->create();

    $record = app(UpsertEventPublicLinkQrConfigAction::class)->execute(
        event: $event,
        linkKey: 'gallery',
        config: [
            'skin_preset' => 'floresta',
        ],
        userId: $user->id,
    );

    expect($record->config_json['skin_preset'])->toBe('floresta')
        ->and($record->config_json['style']['corners_square']['gradient']['type'])->toBe('radial')
        ->and($record->config_json['style']['background']['color'])->toBe('#f7fee7');
});
