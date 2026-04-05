<?php

use App\Modules\MediaProcessing\Services\PipelineFailureClassifier;
use App\Shared\Exceptions\ProviderCircuitOpenException;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\ConnectionException;

it('classifies provider misconfiguration as permanent', function () {
    $classifier = app(PipelineFailureClassifier::class);

    expect($classifier->classify(new ProviderMisconfiguredException('missing api key')))->toBe('permanent')
        ->and($classifier->classify(new \InvalidArgumentException('bad config')))->toBe('permanent');
});

it('classifies connectivity and circuit failures as transient', function () {
    $classifier = app(PipelineFailureClassifier::class);

    expect($classifier->classify(new ConnectionException('timeout')))->toBe('transient')
        ->and($classifier->classify(new ProviderCircuitOpenException('open')))->toBe('transient');
});
