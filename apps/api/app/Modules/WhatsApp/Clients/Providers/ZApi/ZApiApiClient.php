<?php

namespace App\Modules\WhatsApp\Clients\Providers\ZApi;

use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Low-level HTTP client for Z-API.
 *
 * Encapsulates all HTTP communication with Z-API:
 * - Base URL construction: https://api.z-api.io/instances/{id}/token/{token}/
 * - Auth header: Client-Token
 * - Retry with backoff for 429/500
 * - Timeout management
 * - Request/response logging (with token masking)
 */
class ZApiApiClient
{
    private string $baseUrl;
    private int $timeout;
    private int $retries;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('whatsapp.providers.zapi.base_url'), '/');
        $this->timeout = config('whatsapp.providers.zapi.timeout', 30);
        $this->retries = config('whatsapp.providers.zapi.retries', 3);
    }

    // ─── Connection / Instance ─────────────────────────────

    public function getQrCode(WhatsAppInstance $instance): array
    {
        return $this->get($instance, '/qr-code');
    }

    public function getQrCodeImage(WhatsAppInstance $instance): array
    {
        return $this->get($instance, '/qr-code/image');
    }

    public function getPhoneCode(WhatsAppInstance $instance, string $phone): array
    {
        return $this->get($instance, "/phone-code/{$phone}");
    }

    public function getStatus(WhatsAppInstance $instance): array
    {
        return $this->get($instance, '/status');
    }

    public function disconnect(WhatsAppInstance $instance): array
    {
        return $this->get($instance, '/disconnect');
    }

    // ─── Messaging ─────────────────────────────────────────

    public function sendText(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-text', $payload);
    }

    public function sendImage(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-image', $payload);
    }

    public function sendAudio(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-audio', $payload);
    }

    public function sendReaction(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-reaction', $payload);
    }

    public function sendRemoveReaction(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-remove-reaction', $payload);
    }

    public function sendCarousel(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-carousel', $payload);
    }

    public function sendButtonPix(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/send-button-pix', $payload);
    }

    // ─── Chats ─────────────────────────────────────────────

    public function getChats(WhatsAppInstance $instance, int $page = 1, int $pageSize = 20): array
    {
        return $this->get($instance, "/chats?page={$page}&pageSize={$pageSize}");
    }

    public function modifyChat(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/modify-chat', $payload);
    }

    // ─── Groups ────────────────────────────────────────────

    public function createGroup(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/create-group', $payload);
    }

    public function updateGroupName(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/update-group-name', $payload);
    }

    public function updateGroupPhoto(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/update-group-photo', $payload);
    }

    public function updateGroupDescription(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/update-group-description', $payload);
    }

    public function updateGroupSettings(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/update-group-settings', $payload);
    }

    public function getGroupInvitationLink(WhatsAppInstance $instance, string $groupId): array
    {
        return $this->get($instance, "/group-invitation-link/{$groupId}");
    }

    public function addParticipant(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/add-participant', $payload);
    }

    public function removeParticipant(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/remove-participant', $payload);
    }

    public function addAdmin(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/add-admin', $payload);
    }

    public function leaveGroup(WhatsAppInstance $instance, array $payload): array
    {
        return $this->post($instance, '/leave-group', $payload);
    }

    // ─── HTTP Internals ────────────────────────────────────

    private function get(WhatsAppInstance $instance, string $endpoint): array
    {
        $url = $this->buildUrl($instance, $endpoint);
        $startTime = microtime(true);

        try {
            $response = $this->makeRequest($instance)->get($url);

            $this->logRequest('GET', $endpoint, $instance, null, $response, $startTime);

            return $this->parseResponse($response);
        } catch (\Throwable $e) {
            $this->logError('GET', $endpoint, $instance, $e, $startTime);
            throw $e;
        }
    }

    private function post(WhatsAppInstance $instance, string $endpoint, array $payload): array
    {
        $url = $this->buildUrl($instance, $endpoint);
        $startTime = microtime(true);

        try {
            $response = $this->makeRequest($instance)->post($url, $payload);

            $this->logRequest('POST', $endpoint, $instance, $payload, $response, $startTime);

            return $this->parseResponse($response);
        } catch (\Throwable $e) {
            $this->logError('POST', $endpoint, $instance, $e, $startTime);
            throw $e;
        }
    }

    private function makeRequest(WhatsAppInstance $instance): PendingRequest
    {
        return Http::withHeaders([
            'Client-Token' => $instance->provider_client_token,
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->retry($this->retries, 1000, function (\Exception $e, PendingRequest $request) {
                // Retry only on 429 (rate limit) and 5xx errors
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $e->response->status();
                    return $status === 429 || $status >= 500;
                }
                return false;
            });
    }

    private function buildUrl(WhatsAppInstance $instance, string $endpoint): string
    {
        return sprintf(
            '%s/instances/%s/token/%s%s',
            $this->baseUrl,
            $instance->external_instance_id,
            $instance->provider_token,
            $endpoint
        );
    }

    private function parseResponse(Response $response): array
    {
        return [
            'status' => $response->status(),
            'body' => $response->json() ?? [],
            'success' => $response->successful(),
        ];
    }

    // ─── Logging ───────────────────────────────────────────

    private function logRequest(
        string $method,
        string $endpoint,
        WhatsAppInstance $instance,
        ?array $payload,
        Response $response,
        float $startTime,
    ): void {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        Log::channel('whatsapp')->info('Z-API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'instance_id' => $instance->id,
            'provider_key' => 'zapi',
            'http_status' => $response->status(),
            'duration_ms' => $durationMs,
            'success' => $response->successful(),
            'payload' => $this->maskSensitiveData($payload),
        ]);
    }

    private function logError(
        string $method,
        string $endpoint,
        WhatsAppInstance $instance,
        \Throwable $e,
        float $startTime,
    ): void {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        Log::channel('whatsapp')->error('Z-API Request Failed', [
            'method' => $method,
            'endpoint' => $endpoint,
            'instance_id' => $instance->id,
            'provider_key' => 'zapi',
            'duration_ms' => $durationMs,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Mask tokens and sensitive fields in payloads for logging.
     */
    public function maskSensitiveData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (! config('whatsapp.dispatch_log.mask_tokens', true)) {
            return $data;
        }

        $sensitiveKeys = ['token', 'client_token', 'clientToken', 'Client-Token', 'password', 'secret'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value) && in_array($key, $sensitiveKeys, true)) {
                $data[$key] = '***MASKED***';
            }
        }

        return $data;
    }
}
