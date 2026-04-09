<?php

use App\Modules\Events\Models\Event;
use App\Modules\FaceSearch\Services\AwsRekognitionClientFactory;
use App\Modules\FaceSearch\Services\AwsRekognitionFaceSearchBackend;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;
use Mockery as m;

it('returns a healthy aws health check payload with identity, collection status and iam hints', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
        'aws_collection_arn' => 'arn:aws:rekognition:eu-central-1:123456789012:collection/eventovivo-face-search-event-' . $event->id,
        'aws_face_model_version' => '7.0',
    ]);

    $sts = m::mock(StsClient::class);
    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeStsClient')
        ->once()
        ->with(['region' => 'eu-central-1'])
        ->andReturn($sts);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $sts->shouldReceive('getCallerIdentity')
        ->once()
        ->andReturn([
            'Account' => '123456789012',
            'Arn' => 'arn:aws:iam::123456789012:user/eventovivo',
            'UserId' => 'AIDAEXAMPLE',
        ]);

    $client->shouldReceive('describeCollection')
        ->once()
        ->with(['CollectionId' => $settings->aws_collection_id])
        ->andReturn([
            'CollectionARN' => $settings->aws_collection_arn,
            'FaceModelVersion' => '7.0',
            'FaceCount' => 4,
        ]);

    $client->shouldReceive('listFaces')
        ->once()
        ->with([
            'CollectionId' => $settings->aws_collection_id,
            'MaxResults' => 1,
        ])
        ->andReturn([
            'Faces' => [],
        ]);

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $result = $backend->healthCheck($event, $settings);

    expect($result['status'])->toBe('healthy')
        ->and($result['backend_key'])->toBe('aws_rekognition')
        ->and($result['identity']['account'])->toBe('123456789012')
        ->and($result['collection']['face_count'])->toBe(4)
        ->and($result['checks']['identity'])->toBe('ok')
        ->and($result['checks']['collection'])->toBe('ok')
        ->and($result['checks']['list_faces'])->toBe('ok')
        ->and($result['required_actions'])->toContain('rekognition:IndexFaces')
        ->and($result['required_actions'])->toContain('rekognition:SearchFacesByImage');
});

it('returns a misconfigured health check when collection access is denied', function () {
    $event = Event::factory()->create();

    $settings = \Database\Factories\EventFaceSearchSettingFactory::new()->enabled()->create([
        'event_id' => $event->id,
        'recognition_enabled' => true,
        'search_backend_key' => 'aws_rekognition',
        'aws_region' => 'eu-central-1',
        'aws_collection_id' => 'eventovivo-face-search-event-' . $event->id,
    ]);

    $sts = m::mock(StsClient::class);
    $client = m::mock(RekognitionClient::class);
    $factory = m::mock(AwsRekognitionClientFactory::class);

    $factory->shouldReceive('makeStsClient')
        ->once()
        ->with(['region' => 'eu-central-1'])
        ->andReturn($sts);

    $factory->shouldReceive('makeRekognitionClient')
        ->once()
        ->with('query', ['region' => 'eu-central-1'])
        ->andReturn($client);

    $sts->shouldReceive('getCallerIdentity')
        ->once()
        ->andReturn([
            'Account' => '123456789012',
            'Arn' => 'arn:aws:iam::123456789012:user/eventovivo',
            'UserId' => 'AIDAEXAMPLE',
        ]);

    $client->shouldReceive('describeCollection')
        ->once()
        ->andThrow(new AwsException(
            'access denied',
            new Command('DescribeCollection'),
            ['code' => 'AccessDeniedException'],
        ));

    $backend = new AwsRekognitionFaceSearchBackend($factory);

    $result = $backend->healthCheck($event, $settings);

    expect($result['status'])->toBe('misconfigured')
        ->and($result['checks']['identity'])->toBe('ok')
        ->and($result['checks']['collection'])->toBe('failed')
        ->and($result['error_code'])->toBe('AccessDeniedException');
});
