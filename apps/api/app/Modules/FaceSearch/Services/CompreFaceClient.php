<?php

namespace App\Modules\FaceSearch\Services;

use App\Shared\Exceptions\ProviderMisconfiguredException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

class CompreFaceClient
{
    private const DETECTION_ENDPOINT = '/api/v1/detection/detect';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function detectBase64(string $base64Image, array $options = []): array
    {
        $config = $this->validatedConfig();

        $response = $this->baseRequest($config)
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
        $config = $this->validatedConfig();
        $headers = [];

        if ($mimeType !== null && $mimeType !== '') {
            $headers['Content-Type'] = $mimeType;
        }

        $response = $this->baseRequest($config)
            ->asMultipart()
            ->attach('file', $binary, $filename, $headers)
            ->post($this->detectionEndpoint($config, $options));

        return $this->decodeDetectionResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedConfig(): array
    {
        $config = (array) config('face_search.providers.compreface', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($baseUrl === '') {
            throw new ProviderMisconfiguredException('FACE_SEARCH_COMPRE_FACE_BASE_URL is not configured for face search.');
        }

        if ($apiKey === '') {
            throw new ProviderMisconfiguredException('FACE_SEARCH_COMPRE_FACE_API_KEY is not configured for face search.');
        }

        $config['base_url'] = $baseUrl;
        $config['api_key'] = $apiKey;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function baseRequest(array $config): PendingRequest
    {
        return $this->http
            ->baseUrl((string) $config['base_url'])
            ->withHeaders([
                'x-api-key' => (string) $config['api_key'],
            ])
            ->acceptJson()
            ->timeout((int) ($config['timeout'] ?? 15))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5));
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
}
