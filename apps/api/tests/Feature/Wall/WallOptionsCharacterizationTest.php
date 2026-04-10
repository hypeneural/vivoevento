<?php

it('returns wall options with per-layout capability metadata while puzzle remains gated', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('wall.layouts.puzzle.enabled', false);

    $response = $this->apiGet('/wall/options');

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.layouts.0.value', 'auto');

    expect($response->json('data.layouts.0.label'))->toBeString()
        ->and($response->json('data.layouts.0.label'))->not->toBe('');

    expect(collect($response->json('data.layouts'))->pluck('value')->all())->not->toContain('puzzle');

    expect($response->json('data.layouts.0'))->toHaveKey('capabilities')
        ->and($response->json('data.layouts.0.capabilities.supports_video_playback'))->toBeTrue()
        ->and($response->json('data.layouts.0.capabilities.supports_multi_video'))->toBeFalse()
        ->and($response->json('data.layouts.0.capabilities.max_simultaneous_videos'))->toBe(1)
        ->and($response->json('data.layouts.0'))->toHaveKey('defaults');
});
