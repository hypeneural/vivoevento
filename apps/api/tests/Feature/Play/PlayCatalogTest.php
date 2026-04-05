<?php

it('returns the play catalog with memory and puzzle definitions', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/play/catalog');

    $this->assertApiSuccess($response);

    expect(collect($response->json('data'))->pluck('key')->all())
        ->toContain('memory')
        ->toContain('puzzle');
});
