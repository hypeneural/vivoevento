<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Clients\DTOs\ProviderConnectionDetailsData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderQrCodeData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderStatusData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppConnectionStateService
{
    public function __construct(
        private readonly WhatsAppProviderResolver $providerResolver,
    ) {}

    public function build(WhatsAppInstance $instance): array
    {
        $provider = $this->providerResolver->forInstance($instance);
        $status = $this->rememberWithLock(
            $this->cacheKey($instance, 'status'),
            (int) config('whatsapp.cache.status_ttl_seconds', 15),
            fn () => $provider->getStatus($instance),
        );

        if ($status->connected) {
            $details = $this->rememberWithLock(
                $this->cacheKey($instance, 'details'),
                (int) config('whatsapp.cache.details_ttl_seconds', 30),
                fn () => $provider->getConnectionDetails($instance),
            );

            return $this->buildConnectedState($instance, $status, $details);
        }

        $qrError = null;
        $qrData = null;

        try {
            $qrData = $this->rememberWithLock(
                $this->cacheKey($instance, 'qr'),
                (int) config('whatsapp.cache.qr_ttl_seconds', 10),
                fn () => $provider->getQrCodeImage($instance),
            );

            if ($qrData->alreadyConnected) {
                $details = $this->rememberWithLock(
                    $this->cacheKey($instance, 'details'),
                    (int) config('whatsapp.cache.details_ttl_seconds', 30),
                    fn () => $provider->getConnectionDetails($instance),
                );

                return $this->buildConnectedState($instance, $status, $details, 'qr+details');
            }

            if ($qrData->error) {
                $qrError = $qrData->error;
            }
        } catch (\Throwable $e) {
            $qrError = 'Nao foi possivel obter um novo QR Code no momento.';

            Log::channel('whatsapp')->warning('Failed to build connection state QR', [
                'instance_id' => $instance->id,
                'provider_key' => $instance->providerKeyValue(),
                'error' => $e->getMessage(),
            ]);
        }

        return $this->buildDisconnectedState($instance, $status, $qrData, $qrError);
    }

    private function buildConnectedState(
        WhatsAppInstance $instance,
        ProviderStatusData $status,
        ProviderConnectionDetailsData $details,
        string $source = 'status+details',
    ): array {
        $phone = $details->phone ?: $instance->phone_number;

        return [
            'provider' => $instance->providerKeyValue(),
            'connected' => true,
            'checked_at' => now()->toISOString(),
            'connection_source' => $source,
            'instance_status' => $instance->normalizedStatus()->value,
            'smartphone_connected' => $status->smartphoneConnected,
            'status_message' => $details->statusMessage ?? $status->error,
            'phone' => $phone,
            'formatted_phone' => $this->formatPhone($phone),
            'qr_code' => null,
            'qr_render_mode' => null,
            'qr_available' => false,
            'qr_expires_in_sec' => null,
            'qr_error' => null,
            'profile' => $this->normalizeProfile($details->profile),
            'device' => $this->normalizeDevice($details->device),
            'device_error' => $details->error,
            'last_status_sync_at' => $instance->last_status_sync_at?->toISOString(),
            'last_health_check_at' => $instance->last_health_check_at?->toISOString(),
            'last_health_status' => $instance->last_health_status,
            'last_error' => $instance->last_error,
        ];
    }

    private function buildDisconnectedState(
        WhatsAppInstance $instance,
        ProviderStatusData $status,
        ?ProviderQrCodeData $qrData,
        ?string $qrError,
    ): array {
        $renderMode = $qrData?->renderMode();
        $qrPayload = $this->normalizeQrPayload($qrData);

        return [
            'provider' => $instance->providerKeyValue(),
            'connected' => false,
            'checked_at' => now()->toISOString(),
            'connection_source' => 'status+qr',
            'instance_status' => $instance->normalizedStatus()->value,
            'smartphone_connected' => $status->smartphoneConnected,
            'status_message' => $status->error,
            'phone' => $instance->phone_number,
            'formatted_phone' => $this->formatPhone($instance->phone_number),
            'qr_code' => $qrPayload,
            'qr_render_mode' => $renderMode,
            'qr_available' => filled($qrPayload),
            'qr_expires_in_sec' => filled($qrPayload)
                ? (int) config('whatsapp.qr_code.expires_in_seconds', 20)
                : null,
            'qr_error' => $qrError,
            'profile' => $this->emptyProfile(),
            'device' => $this->emptyDevice(),
            'device_error' => null,
            'last_status_sync_at' => $instance->last_status_sync_at?->toISOString(),
            'last_health_check_at' => $instance->last_health_check_at?->toISOString(),
            'last_health_status' => $instance->last_health_status,
            'last_error' => $instance->last_error,
        ];
    }

    private function normalizeQrPayload(?ProviderQrCodeData $qrData): ?string
    {
        if (! $qrData) {
            return null;
        }

        $payload = $qrData->payload();

        if (! $payload) {
            return null;
        }

        if ($qrData->renderMode() === 'image' && ! str_starts_with($payload, 'data:image')) {
            return 'data:image/png;base64,' . $payload;
        }

        return $payload;
    }

    private function normalizeProfile(array $profile): array
    {
        return [
            'lid' => $profile['lid'] ?? null,
            'name' => $profile['name'] ?? null,
            'about' => $profile['about'] ?? null,
            'img_url' => $profile['img_url'] ?? null,
            'is_business' => $profile['is_business'] ?? false,
        ];
    }

    private function normalizeDevice(array $device): array
    {
        return [
            'session_id' => $device['session_id'] ?? null,
            'session_name' => $device['session_name'] ?? null,
            'device_model' => $device['device_model'] ?? null,
            'original_device' => $device['original_device'] ?? null,
        ];
    }

    private function emptyProfile(): array
    {
        return $this->normalizeProfile([]);
    }

    private function emptyDevice(): array
    {
        return $this->normalizeDevice([]);
    }

    private function formatPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            $ddd = substr($digits, 2, 2);
            $local = substr($digits, 4);

            return strlen($local) === 9
                ? sprintf('(%s) %s-%s', $ddd, substr($local, 0, 5), substr($local, 5))
                : sprintf('(%s) %s-%s', $ddd, substr($local, 0, 4), substr($local, 4));
        }

        return '+' . $digits;
    }

    private function rememberWithLock(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $lock = Cache::lock("lock:{$key}", (int) config('whatsapp.cache.lock_seconds', 5));

        return $lock->block(3, function () use ($key, $ttlSeconds, $callback) {
            if (Cache::has($key)) {
                return Cache::get($key);
            }

            $value = $callback();
            Cache::put($key, $value, $ttlSeconds);

            return $value;
        });
    }

    private function cacheKey(WhatsAppInstance $instance, string $suffix): string
    {
        return "whatsapp:instance:{$instance->id}:{$suffix}";
    }
}
