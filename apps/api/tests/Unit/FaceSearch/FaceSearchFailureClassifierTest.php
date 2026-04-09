<?php

use App\Modules\FaceSearch\Services\FaceSearchFailureClassifier;
use App\Shared\Exceptions\ProviderCircuitOpenException;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Validation\ValidationException;

it('classifies throttling and throughput aws failures as transient throttled errors', function () {
    $classifier = app(FaceSearchFailureClassifier::class);

    $throttled = new AwsException('throttled', new Command('SearchFacesByImage'), [
        'code' => 'ThrottlingException',
    ]);

    $throughput = new AwsException('throughput', new Command('SearchFacesByImage'), [
        'code' => 'ProvisionedThroughputExceededException',
    ]);

    expect($classifier->classify($throttled))->toBe([
        'failure_class' => 'transient',
        'reason_code' => 'throttled',
    ])->and($classifier->classify($throughput))->toBe([
        'failure_class' => 'transient',
        'reason_code' => 'throttled',
    ]);
});

it('classifies no-face and group-photo validation failures as permanent functional errors', function () {
    $classifier = app(FaceSearchFailureClassifier::class);

    $validation = ValidationException::withMessages([
        'selfie' => ['Envie uma selfie com apenas uma pessoa visivel. Busca por foto de grupo ainda nao faz parte desta versao.'],
    ]);

    expect($classifier->classify($validation))->toBe([
        'failure_class' => 'permanent',
        'reason_code' => 'functional_no_face',
    ]);
});

it('classifies access problems and circuit-open failures into the repo failure language', function () {
    $classifier = app(FaceSearchFailureClassifier::class);

    $accessDenied = new AwsException('access denied', new Command('SearchFacesByImage'), [
        'code' => 'AccessDeniedException',
    ]);

    expect($classifier->classify($accessDenied))->toBe([
        'failure_class' => 'permanent',
        'reason_code' => 'misconfigured',
    ])->and($classifier->classify(new ProviderCircuitOpenException('open')))->toBe([
        'failure_class' => 'transient',
        'reason_code' => 'provider_unavailable',
    ]);
});
