<?php

use App\Modules\FaceSearch\Services\FaceSearchBackendInterface;
use App\Modules\FaceSearch\Services\FaceSearchRouter;

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('defines a search backend abstraction and a router above provider and vector store', function () {
    expect(interface_exists(FaceSearchBackendInterface::class))->toBeTrue()
        ->and(class_exists(FaceSearchRouter::class))->toBeTrue();

    $router = app(FaceSearchRouter::class);

    expect($router)->toBeInstanceOf(FaceSearchRouter::class);
});
