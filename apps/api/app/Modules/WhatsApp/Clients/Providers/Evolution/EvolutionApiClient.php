<?php

namespace App\Modules\WhatsApp\Clients\Providers\Evolution;

use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionApiClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('whatsapp.providers.evolution.base_url'), '/');
        $this->timeout = (int) config('whatsapp.providers.evolution.timeout', 30);
    }

    public function getStatus(WhatsAppInstance $instance): array
    {
        return $this->request($instance, 'GET', '/instance/connectionState/' . rawurlencode($instance->providerInstanceKey()));
    }

    public function connect(WhatsAppInstance $instance, ?string $phone = null): array
    {
        $params = $phone ? ['number' => $phone] : [];

        return $this->request(
            $instance,
            'GET',
            '/instance/connect/' . rawurlencode($instance->providerInstanceKey()),
            params: $params,
        );
    }

    public function fetchInstances(WhatsAppInstance $instance): array
    {
        return $this->request($instance, 'GET', '/instance/fetchInstances');
    }

    public function sendText(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/message/sendText/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function sendMedia(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/message/sendMedia/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function sendWhatsAppAudio(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/message/sendWhatsAppAudio/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function sendReaction(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/message/sendReaction/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function findChats(WhatsAppInstance $instance): array
    {
        return $this->request($instance, 'POST', '/chat/findChats/' . rawurlencode($instance->providerInstanceKey()));
    }

    public function findMessages(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/chat/findMessages/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function markMessageAsRead(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/chat/markMessageAsRead/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function markChatUnread(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/chat/markChatUnread/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function archiveChat(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/chat/archiveChat/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function createGroup(WhatsAppInstance $instance, array $payload): array
    {
        return $this->request($instance, 'POST', '/group/create/' . rawurlencode($instance->providerInstanceKey()), payload: $payload);
    }

    public function updateGroupPicture(WhatsAppInstance $instance, string $groupJid, array $payload): array
    {
        return $this->request(
            $instance,
            'POST',
            '/group/updateGroupPicture/' . rawurlencode($instance->providerInstanceKey()),
            payload: $payload,
            params: ['groupJid' => $groupJid],
        );
    }

    public function updateGroupSubject(WhatsAppInstance $instance, string $groupJid, array $payload): array
    {
        return $this->request(
            $instance,
            'POST',
            '/group/updateGroupSubject/' . rawurlencode($instance->providerInstanceKey()),
            payload: $payload,
            params: ['groupJid' => $groupJid],
        );
    }

    public function updateGroupDescription(WhatsAppInstance $instance, string $groupJid, array $payload): array
    {
        return $this->request(
            $instance,
            'POST',
            '/group/updateGroupDescription/' . rawurlencode($instance->providerInstanceKey()),
            payload: $payload,
            params: ['groupJid' => $groupJid],
        );
    }

    public function fetchInviteCode(WhatsAppInstance $instance, string $groupJid): array
    {
        return $this->request(
            $instance,
            'GET',
            '/group/inviteCode/' . rawurlencode($instance->providerInstanceKey()),
            params: ['groupJid' => $groupJid],
        );
    }

    public function fetchAllGroups(WhatsAppInstance $instance, bool $getParticipants = false): array
    {
        return $this->request(
            $instance,
            'GET',
            '/group/fetchAllGroups/' . rawurlencode($instance->providerInstanceKey()),
            params: ['getParticipants' => $getParticipants ? 'true' : 'false'],
        );
    }

    public function updateParticipant(WhatsAppInstance $instance, string $groupJid, array $payload): array
    {
        return $this->request(
            $instance,
            'POST',
            '/group/updateParticipant/' . rawurlencode($instance->providerInstanceKey()),
            payload: $payload,
            params: ['groupJid' => $groupJid],
        );
    }

    public function updateSetting(WhatsAppInstance $instance, string $groupJid, array $payload): array
    {
        return $this->request(
            $instance,
            'POST',
            '/group/updateSetting/' . rawurlencode($instance->providerInstanceKey()),
            payload: $payload,
            params: ['groupJid' => $groupJid],
        );
    }

    public function logout(WhatsAppInstance $instance): array
    {
        return $this->request($instance, 'DELETE', '/instance/logout/' . rawurlencode($instance->providerInstanceKey()));
    }

    public function leaveGroup(WhatsAppInstance $instance, string $groupJid): array
    {
        return $this->request(
            $instance,
            'DELETE',
            '/group/leaveGroup/' . rawurlencode($instance->providerInstanceKey()),
            params: ['groupJid' => $groupJid],
        );
    }

    private function request(
        WhatsAppInstance $instance,
        string $method,
        string $endpoint,
        ?array $payload = null,
        array $params = [],
    ): array {
        $url = $this->buildUrl($instance, $endpoint);
        $startTime = microtime(true);

        try {
            $request = $this->makeRequest($instance);
            $url = $this->appendQueryString($url, $params);
            $response = match ($method) {
                'DELETE' => $request->delete($url),
                'POST' => $request->post($url, $payload ?? []),
                default => $request->get($url),
            };

            $this->logRequest($method, $endpoint, $instance, $payload, $response, $startTime);

            return $this->parseResponse($response);
        } catch (\Throwable $e) {
            $this->logError($method, $endpoint, $instance, $e, $startTime);
            throw $e;
        }
    }

    private function makeRequest(WhatsAppInstance $instance): PendingRequest
    {
        $config = $instance->providerConfig();
        $apiKey = (string) ($config['api_key'] ?? config('whatsapp.providers.evolution.api_key', ''));

        return Http::withHeaders(array_filter([
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
        ], fn ($value) => $value !== null && $value !== ''))
            ->timeout($this->timeout);
    }

    private function buildUrl(WhatsAppInstance $instance, string $endpoint): string
    {
        $config = $instance->providerConfig();
        $baseUrl = rtrim((string) ($config['server_url'] ?? $this->baseUrl), '/');

        return $baseUrl . $endpoint;
    }

    private function parseResponse(Response $response): array
    {
        $body = $response->json();

        return [
            'status' => $response->status(),
            'body' => is_array($body) ? $body : ['raw' => $response->body()],
            'success' => $response->successful(),
        ];
    }

    private function logRequest(
        string $method,
        string $endpoint,
        WhatsAppInstance $instance,
        ?array $payload,
        Response $response,
        float $startTime,
    ): void {
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        Log::channel('whatsapp')->info('Evolution API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'instance_id' => $instance->id,
            'provider_key' => 'evolution',
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

        Log::channel('whatsapp')->error('Evolution API Request Failed', [
            'method' => $method,
            'endpoint' => $endpoint,
            'instance_id' => $instance->id,
            'provider_key' => 'evolution',
            'duration_ms' => $durationMs,
            'error' => $e->getMessage(),
        ]);
    }

    private function maskSensitiveData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sensitiveKeys = ['api_key', 'apikey', 'token', 'client_token', 'password', 'secret'];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value) && in_array($key, $sensitiveKeys, true)) {
                $data[$key] = '***MASKED***';
            }
        }

        return $data;
    }

    private function appendQueryString(string $url, array $params): string
    {
        $filtered = array_filter(
            $params,
            static fn ($value) => $value !== null && $value !== ''
        );

        if ($filtered === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($filtered);
    }
}
