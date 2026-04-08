<?php

use App\Modules\FaceSearch\Actions\SearchFacesBySelfieAction;
use App\Modules\FaceSearch\Services\FaceSearchRouter;

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('routes selfie search through a backend router instead of directly coupling to detector embedder and vector store', function () {
    $reflection = new \ReflectionClass(SearchFacesBySelfieAction::class);
    $constructor = $reflection->getConstructor();

    expect($constructor)->not->toBeNull();

    $parameterTypes = collect($constructor?->getParameters() ?? [])
        ->map(fn (\ReflectionParameter $parameter) => $parameter->getType()?->getName())
        ->filter()
        ->values()
        ->all();

    expect($parameterTypes)->toContain(FaceSearchRouter::class)
        ->not->toContain(\App\Modules\FaceSearch\Services\FaceDetectionProviderInterface::class)
        ->not->toContain(\App\Modules\FaceSearch\Services\FaceEmbeddingProviderInterface::class)
        ->not->toContain(\App\Modules\FaceSearch\Services\FaceVectorStoreInterface::class);
});
