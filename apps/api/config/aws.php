<?php

use Aws\Laravel\AwsServiceProvider;

$credentials = array_filter([
    'key' => env('FACE_SEARCH_AWS_ACCESS_KEY_ID'),
    'secret' => env('FACE_SEARCH_AWS_SECRET_ACCESS_KEY'),
    'token' => env('FACE_SEARCH_AWS_SESSION_TOKEN'),
], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

$config = [
    'region' => env('FACE_SEARCH_AWS_REGION', env('FACE_SEARCH_AWS_DEFAULT_REGION', 'eu-central-1')),
    'version' => 'latest',
    'ua_append' => [
        'L5MOD/' . AwsServiceProvider::VERSION,
        'EVENTOVIVO-FACESEARCH/1',
    ],
];

if ($credentials !== []) {
    $config['credentials'] = $credentials;
}

$endpoint = env('FACE_SEARCH_AWS_ENDPOINT');

if (is_string($endpoint) && trim($endpoint) !== '') {
    $config['endpoint'] = trim($endpoint);
}

return $config;
