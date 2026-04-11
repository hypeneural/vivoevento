<?php

it('returns wall transition modes and defaults separately from transition effects', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/wall/options');

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.transition_modes.0.value', 'fixed')
        ->assertJsonPath('data.transition_modes.0.label', 'Fixa')
        ->assertJsonPath('data.transition_modes.1.value', 'random')
        ->assertJsonPath('data.transition_modes.1.label', 'Aleatoria')
        ->assertJsonPath('data.transition_defaults.transition_effect', 'fade')
        ->assertJsonPath('data.transition_defaults.transition_mode', 'fixed');

    $transitionEffects = collect($response->json('data.transitions'))->pluck('value')->all();

    expect($transitionEffects)->toBe([
        'fade',
        'slide',
        'zoom',
        'flip',
        'lift-fade',
        'cross-zoom',
        'swipe-up',
        'none',
    ])->not->toContain('random');
});
