<?php

namespace App\Modules\FaceSearch\Services;

use Aws\Sdk;
use Aws\Credentials\Credentials;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;

class AwsRekognitionClientFactory
{
    public function __construct(
        private readonly ?Sdk $sdk = null,
    ) {}

    /**
     * @param array<string, mixed> $overrides
     */
    public function makeRekognitionClient(string $profile = 'query', array $overrides = []): RekognitionClient
    {
        $config = $this->rekognitionConfig($profile, $overrides);

        if ($this->sdk instanceof Sdk) {
            /** @var RekognitionClient $client */
            $client = $this->sdk->createRekognition($config);

            return $client;
        }

        return new RekognitionClient($config);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public function makeStsClient(array $overrides = []): StsClient
    {
        $config = $this->stsConfig($overrides);

        if ($this->sdk instanceof Sdk) {
            /** @var StsClient $client */
            $client = $this->sdk->createSts($config);

            return $client;
        }

        return new StsClient($config);
    }

    /**
     * @return array<string, mixed>
     * @param array<string, mixed> $overrides
     */
    public function rekognitionConfig(string $profile = 'query', array $overrides = []): array
    {
        $config = array_replace((array) config('face_search.providers.aws_rekognition', []), $overrides);

        return array_filter([
            'version' => (string) ($config['version'] ?? '2016-06-27'),
            'region' => (string) ($config['region'] ?? 'eu-central-1'),
            'credentials' => $this->credentials($config),
            'endpoint' => $this->nonEmptyString($config['endpoint'] ?? null),
            'retries' => [
                'mode' => (string) ($config['retry_mode'] ?? 'standard'),
                'max_attempts' => $profile === 'index'
                    ? (int) ($config['max_attempts_index'] ?? 5)
                    : (int) ($config['max_attempts_query'] ?? 3),
            ],
            'http' => [
                'connect_timeout' => $this->connectTimeoutForProfile($config, $profile),
                'timeout' => $profile === 'index'
                    ? (float) ($config['index_timeout'] ?? 15)
                    : (float) ($config['query_timeout'] ?? 8),
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     * @param array<string, mixed> $overrides
     */
    public function stsConfig(array $overrides = []): array
    {
        $config = array_replace((array) config('face_search.providers.aws_rekognition', []), $overrides);

        return array_filter([
            'version' => (string) ($config['sts_version'] ?? '2011-06-15'),
            'region' => (string) ($config['region'] ?? 'eu-central-1'),
            'credentials' => $this->credentials($config),
            'endpoint' => $this->nonEmptyString($config['endpoint'] ?? null),
            'retries' => [
                'mode' => (string) ($config['retry_mode'] ?? 'standard'),
                'max_attempts' => (int) ($config['max_attempts_query'] ?? 3),
            ],
            'http' => [
                'connect_timeout' => $this->connectTimeoutForProfile($config, 'query'),
                'timeout' => (float) ($config['query_timeout'] ?? 8),
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function credentials(array $config): ?Credentials
    {
        $accessKey = $this->nonEmptyString($config['access_key_id'] ?? null);
        $secretKey = $this->nonEmptyString($config['secret_access_key'] ?? null);

        if ($accessKey === null || $secretKey === null) {
            return null;
        }

        return new Credentials(
            $accessKey,
            $secretKey,
            $this->nonEmptyString($config['session_token'] ?? null),
        );
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function connectTimeoutForProfile(array $config, string $profile): float
    {
        $profileSpecificKey = $profile === 'index'
            ? 'connect_timeout_index'
            : 'connect_timeout_query';

        if (isset($config[$profileSpecificKey]) && is_numeric($config[$profileSpecificKey])) {
            return (float) $config[$profileSpecificKey];
        }

        return (float) ($config['connect_timeout'] ?? 3);
    }
}
