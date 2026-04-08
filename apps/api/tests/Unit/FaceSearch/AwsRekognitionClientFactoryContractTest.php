<?php

use App\Modules\FaceSearch\Services\AwsRekognitionClientFactory;

beforeEach(function () {
    if (! filter_var(env('RUN_FACE_SEARCH_AWS_TDD', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('AWS FaceSearch TDD contracts are opt-in. Set RUN_FACE_SEARCH_AWS_TDD=1 to execute them.');
    }
});

it('registers an aws rekognition client factory in the container', function () {
    expect(class_exists(AwsRekognitionClientFactory::class))->toBeTrue();

    $factory = app(AwsRekognitionClientFactory::class);

    expect($factory)->toBeInstanceOf(AwsRekognitionClientFactory::class);
});
