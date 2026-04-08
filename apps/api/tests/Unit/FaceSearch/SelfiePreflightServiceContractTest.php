<?php

use App\Modules\FaceSearch\Services\SelfiePreflightService;

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('defines a dedicated selfie preflight service before spending aws search requests', function () {
    expect(class_exists(SelfiePreflightService::class))->toBeTrue();

    $service = app(SelfiePreflightService::class);

    expect($service)->toBeInstanceOf(SelfiePreflightService::class)
        ->and(method_exists($service, 'validateForSearch'))->toBeTrue();
});
