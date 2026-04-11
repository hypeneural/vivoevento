<?php

it('returns wall options with per-layout capability metadata while puzzle stays officially enabled', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('wall.layouts.puzzle.enabled', false);

    $response = $this->apiGet('/wall/options');

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.layouts.0.value', 'auto');

    expect($response->json('data.layouts.0.label'))->toBeString()
        ->and($response->json('data.layouts.0.label'))->not->toBe('');

    expect(collect($response->json('data.layouts'))->pluck('value')->all())->toContain('puzzle');

    expect($response->json('data.layouts.0'))->toHaveKey('capabilities')
        ->and($response->json('data.layouts.0.capabilities.supports_video_playback'))->toBeTrue()
        ->and($response->json('data.layouts.0.capabilities.supports_multi_video'))->toBeFalse()
        ->and($response->json('data.layouts.0.capabilities.max_simultaneous_videos'))->toBe(1)
        ->and($response->json('data.layouts.0'))->toHaveKey('defaults');
});

it('returns the current wall transition contract without random mode', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/wall/options');

    $this->assertApiSuccess($response);

    $transitions = collect($response->json('data.transitions'));

    expect($transitions->pluck('value')->all())->toBe([
        'fade',
        'slide',
        'zoom',
        'flip',
        'none',
    ])->not->toContain('rand')
        ->not->toContain('random');

    expect($transitions)->toHaveCount(5);
});
