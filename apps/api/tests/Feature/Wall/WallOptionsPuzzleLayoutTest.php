<?php

it('hides puzzle from wall options while the rollout gate is disabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('wall.layouts.puzzle.enabled', false);

    $response = $this->apiGet('/wall/options');

    $this->assertApiSuccess($response);

    $layouts = collect($response->json('data.layouts'));

    expect($layouts->pluck('value')->all())->not->toContain('puzzle')
        ->and($layouts->first())->toHaveKey('capabilities');
});

it('returns puzzle with explicit capabilities when the rollout gate is enabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('wall.layouts.puzzle.enabled', true);

    $response = $this->apiGet('/wall/options');

    $this->assertApiSuccess($response);

    $layout = collect($response->json('data.layouts'))
        ->firstWhere('value', 'puzzle');

    expect($layout)->not->toBeNull()
        ->and($layout['label'])->toBe('Quebra Cabeca')
        ->and($layout['capabilities'])->toMatchArray([
            'supports_video_playback' => false,
            'supports_video_poster_only' => false,
            'supports_multi_video' => false,
            'max_simultaneous_videos' => 0,
            'fallback_video_layout' => 'cinematic',
            'supports_side_thumbnails' => false,
            'supports_floating_caption' => false,
            'supports_theme_config' => true,
        ]);
});
