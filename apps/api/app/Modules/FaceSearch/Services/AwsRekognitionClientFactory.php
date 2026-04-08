<?php

namespace App\Modules\FaceSearch\Services;

use Aws\Credentials\Credentials;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;

class AwsRekognitionClientFactory
{
    public function makeRekognitionClient(string $profile = 'query'): RekognitionClient
    {
        return new RekognitionClient($this->rekognitionConfig($profile));
    }

    public function makeStsClient(): StsClient
    {
        return new StsClient($this->stsConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public function rekognitionConfig(string $profile = 'query'): array
    {
        $config = (array) config('face_search.providers.aws_rekognition', []);

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
                'connect_timeout' => (float) ($config['connect_timeout'] ?? 3),
                'timeout' => $profile === 'index'
                    ? (float) ($config['index_timeout'] ?? 15)
                    : (float) ($config['query_timeout'] ?? 8),
            ],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function stsConfig(): array
    {
        $config = (array) config('face_search.providers.aws_rekognition', []);

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
                'connect_timeout' => (float) ($config['connect_timeout'] ?? 3),
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
}
