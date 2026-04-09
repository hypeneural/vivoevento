<?php

namespace App\Modules\FaceSearch\Services;

use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

class CompreFaceClient
{
    private const DETECTION_ENDPOINT = '/api/v1/detection/detect';
    private const VERIFY_EMBEDDINGS_ENDPOINT = '/api/v1/verification/embeddings/verify';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function detectBase64(string $base64Image, array $options = []): array
    {
        $config = $this->validatedConfig('detection');

        $response = $this->baseRequest($config, 'detection')
            ->contentType('application/json')
            ->post($this->detectionEndpoint($config, $options), [
                'file' => $base64Image,
            ]);

        return $this->decodeDetectionResponse($response);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function detectMultipart(
        string $binary,
        string $filename = 'face-search-image.jpg',
        ?string $mimeType = null,
        array $options = [],
    ): array {
        $config = $this->validatedConfig('detection');
        $headers = [];

        if ($mimeType !== null && $mimeType !== '') {
            $headers['Content-Type'] = $mimeType;
        }

        $response = $this->baseRequest($config, 'detection')
            ->asMultipart()
            ->attach('file', $binary, $filename, $headers)
            ->post($this->detectionEndpoint($config, $options));

        return $this->decodeDetectionResponse($response);
    }

    /**
     * @param array<int, float|int> $sourceEmbedding
     * @param array<int, array<int, float|int>> $targetEmbeddings
     * @return array<string, mixed>
     */
    public function verifyEmbeddings(array $sourceEmbedding, array $targetEmbeddings): array
    {
        $config = $this->validatedConfig('verification');

        $response = $this->baseRequest($config, 'verification')
            ->contentType('application/json')
            ->post(self::VERIFY_EMBEDDINGS_ENDPOINT, [
                'source' => array_values($sourceEmbedding),
                'targets' => array_values($targetEmbeddings),
            ]);

        return $this->decodeDetectionResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedConfig(string $purpose): array
    {
        $config = (array) config('face_search.providers.compreface', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $apiKey = $this->resolvedApiKey($config, $purpose);

        if ($baseUrl === '') {
            throw new ProviderMisconfiguredException('FACE_SEARCH_COMPRE_FACE_BASE_URL is not configured for face search.');
        }

        if ($apiKey === '') {
            throw new ProviderMisconfiguredException($this->missingApiKeyMessage($purpose));
        }

        $config['base_url'] = $baseUrl;
        $config['resolved_api_key'] = $apiKey;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function baseRequest(array $config, string $profile): PendingRequest
    {
        $request = $this->http
            ->baseUrl((string) $config['base_url'])
            ->withHeaders([
                'x-api-key' => (string) $config['resolved_api_key'],
            ])
            ->acceptJson()
            ->timeout($this->requestTimeout($config, $profile))
            ->connectTimeout($this->requestConnectTimeout($config, $profile));

        $retryTimes = $this->requestRetryTimes($config, $profile);

        if ($retryTimes > 1) {
            $request = $request->retry(
                $retryTimes,
                $this->requestRetrySleepMs($config, $profile),
                fn (\Throwable $exception): bool => $this->shouldRetryRequest($exception),
            );
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requestTimeout(array $config, string $profile): int
    {
        $key = $profile === 'verification' ? 'verification_timeout' : 'detection_timeout';

        return (int) ($config[$key] ?? $config['timeout'] ?? 15);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requestConnectTimeout(array $config, string $profile): int
    {
        $key = $profile === 'verification' ? 'verification_connect_timeout' : 'detection_connect_timeout';

        return (int) ($config[$key] ?? $config['connect_timeout'] ?? 5);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requestRetryTimes(array $config, string $profile): int
    {
        $key = $profile === 'verification' ? 'verification_retry_times' : 'detection_retry_times';

        return max(1, (int) ($config[$key] ?? 1));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function requestRetrySleepMs(array $config, string $profile): int
    {
        $key = $profile === 'verification' ? 'verification_retry_sleep_ms' : 'detection_retry_sleep_ms';

        return max(0, (int) ($config[$key] ?? 0));
    }

    private function shouldRetryRequest(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'temporarily unavailable');
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $options
     */
    private function detectionEndpoint(array $config, array $options): string
    {
        $query = [
            'face_plugins' => $options['face_plugins'] ?? $config['face_plugins'] ?? 'calculator,landmarks',
            'det_prob_threshold' => $options['det_prob_threshold'] ?? $config['det_prob_threshold'] ?? null,
            'status' => $options['status'] ?? $config['status'] ?? true,
            'limit' => $options['limit'] ?? null,
        ];

        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');
        $query = array_map(static function (mixed $value): string {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return (string) $value;
        }, $query);

        if ($query === []) {
            return self::DETECTION_ENDPOINT;
        }

        return self::DETECTION_ENDPOINT . '?' . http_build_query($query);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeDetectionResponse(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'CompreFace detection request failed with status %d: %s',
                $response->status(),
                $this->errorMessage($response),
            ));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('CompreFace detection response did not contain a valid JSON payload.');
        }

        return $payload;
    }

    private function errorMessage(Response $response): string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            foreach (['message', 'error', 'detail'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key]) && $payload[$key] !== '') {
                    return $payload[$key];
                }
            }
        }

        $body = trim($response->body());

        return $body !== '' ? $body : 'empty provider response';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolvedApiKey(array $config, string $purpose): string
    {
        $dedicatedKey = match ($purpose) {
            'verification' => (string) ($config['verification_api_key'] ?? ''),
            default => (string) ($config['detection_api_key'] ?? ''),
        };

        if ($dedicatedKey !== '') {
            return $dedicatedKey;
        }

        return (string) ($config['api_key'] ?? '');
    }

    private function missingApiKeyMessage(string $purpose): string
    {
        return match ($purpose) {
            'verification' => 'FACE_SEARCH_COMPRE_FACE_VERIFICATION_API_KEY or FACE_SEARCH_COMPRE_FACE_API_KEY is not configured for face search verification.',
            default => 'FACE_SEARCH_COMPRE_FACE_DETECTION_API_KEY or FACE_SEARCH_COMPRE_FACE_API_KEY is not configured for face search detection.',
        };
    }
}
