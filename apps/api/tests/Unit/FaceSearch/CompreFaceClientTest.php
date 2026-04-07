<?php

use App\Modules\FaceSearch\Services\CompreFaceClient;
use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('calls compreface detection with base64 json payload and api key header', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'det_prob_threshold' => '0.75',
        'status' => true,
        'timeout' => 9,
        'connect_timeout' => 3,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [
                [
                    'box' => [
                        'probability' => 0.99,
                        'x_min' => 10,
                        'y_min' => 20,
                        'x_max' => 110,
                        'y_max' => 140,
                    ],
                    'embedding' => [0.1, 0.2, 0.3],
                ],
            ],
        ]),
    ]);

    $payload = app(CompreFaceClient::class)->detectBase64('YmFzZTY0LWltYWdl');

    expect($payload['result'][0]['embedding'])->toBe([0.1, 0.2, 0.3]);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_starts_with((string) $request->url(), 'http://compreface.test/api/v1/detection/detect')
            && str_contains((string) $request->url(), 'face_plugins=calculator%2Clandmarks')
            && str_contains((string) $request->url(), 'det_prob_threshold=0.75')
            && str_contains((string) $request->url(), 'status=true')
            && $request->hasHeader('x-api-key', 'test-api-key')
            && $request['file'] === 'YmFzZTY0LWltYWdl';
    });
});

it('supports multipart detection requests as a technical fallback', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'status' => false,
        'timeout' => 9,
        'connect_timeout' => 3,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'result' => [],
        ]),
    ]);

    $payload = app(CompreFaceClient::class)->detectMultipart('binary-image', 'selfie.jpg', 'image/jpeg');

    expect($payload['result'])->toBe([]);

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_starts_with((string) $request->url(), 'http://compreface.test/api/v1/detection/detect')
            && str_contains((string) $request->url(), 'face_plugins=calculator%2Clandmarks')
            && str_contains((string) $request->url(), 'status=false')
            && $request->hasHeader('x-api-key', 'test-api-key')
            && str_contains((string) ($request->header('Content-Type')[0] ?? ''), 'multipart/form-data');
    });
});

it('throws a useful exception when compreface returns an error', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => 'test-api-key',
        'face_plugins' => 'calculator,landmarks',
        'timeout' => 9,
        'connect_timeout' => 3,
    ]);

    Http::fake([
        'http://compreface.test/api/v1/detection/detect*' => Http::response([
            'message' => 'embedding server unavailable',
        ], 503),
    ]);

    app(CompreFaceClient::class)->detectBase64('YmFzZTY0LWltYWdl');
})->throws(RuntimeException::class, 'CompreFace detection request failed with status 503');

it('fails fast when compreface credentials are missing', function () {
    config()->set('face_search.providers.compreface', [
        'base_url' => 'http://compreface.test',
        'api_key' => '',
        'face_plugins' => 'calculator,landmarks',
    ]);

    app(CompreFaceClient::class)->detectBase64('YmFzZTY0LWltYWdl');
})->throws(ProviderMisconfiguredException::class, 'FACE_SEARCH_COMPRE_FACE_API_KEY is not configured');
