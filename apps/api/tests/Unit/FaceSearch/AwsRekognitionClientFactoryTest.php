<?php

use App\Modules\FaceSearch\Services\AwsRekognitionClientFactory;

it('builds separated query and index client configs for aws rekognition', function () {
    config()->set('face_search.providers.aws_rekognition', [
        'region' => 'eu-central-1',
        'version' => '2016-06-27',
        'sts_version' => '2011-06-15',
        'retry_mode' => 'standard',
        'max_attempts_query' => 3,
        'max_attempts_index' => 5,
        'connect_timeout' => 3.0,
        'query_timeout' => 8.0,
        'index_timeout' => 15.0,
        'access_key_id' => 'test-key',
        'secret_access_key' => 'test-secret',
        'session_token' => '',
        'endpoint' => '',
    ]);

    $factory = new AwsRekognitionClientFactory;

    $queryConfig = $factory->rekognitionConfig('query');
    $indexConfig = $factory->rekognitionConfig('index');
    $stsConfig = $factory->stsConfig();

    expect($queryConfig['region'])->toBe('eu-central-1')
        ->and($queryConfig['version'])->toBe('2016-06-27')
        ->and($queryConfig['retries']['mode'])->toBe('standard')
        ->and($queryConfig['retries']['max_attempts'])->toBe(3)
        ->and($queryConfig['http']['connect_timeout'])->toBe(3.0)
        ->and($queryConfig['http']['timeout'])->toBe(8.0)
        ->and($queryConfig)->toHaveKey('credentials')
        ->and($indexConfig['retries']['max_attempts'])->toBe(5)
        ->and($indexConfig['http']['timeout'])->toBe(15.0)
        ->and($stsConfig['version'])->toBe('2011-06-15')
        ->and($stsConfig['retries']['max_attempts'])->toBe(3);
});

it('omits explicit credentials when aws face search keys are not configured', function () {
    config()->set('face_search.providers.aws_rekognition', [
        'region' => 'eu-central-1',
        'version' => '2016-06-27',
        'retry_mode' => 'standard',
        'max_attempts_query' => 3,
        'max_attempts_index' => 5,
        'connect_timeout' => 3.0,
        'query_timeout' => 8.0,
        'index_timeout' => 15.0,
        'access_key_id' => '',
        'secret_access_key' => '',
        'endpoint' => '',
    ]);

    $factory = new AwsRekognitionClientFactory;

    expect($factory->rekognitionConfig('query'))->not->toHaveKey('credentials')
        ->and($factory->stsConfig())->not->toHaveKey('credentials');
});
