<?php

namespace App\Modules\WhatsApp\Actions;

use App\Modules\WhatsApp\Clients\DTOs\ProviderHealthCheckData;
use App\Modules\WhatsApp\Enums\InstanceStatus;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;

class TestWhatsAppInstanceConnectionAction
{
    public function __construct(
        private readonly WhatsAppProviderResolver $providerResolver,
    ) {}

    public function execute(WhatsAppInstance $instance): array
    {
        $provider = $this->providerResolver->forInstance($instance);
        $result = $provider->testConnection($instance);

        $this->persistResult($instance, $result);

        return [
            'success' => $result->success,
            'connected' => $result->connected,
            'status' => $result->status,
            'message' => $result->message,
            'error' => $result->error,
            'checked_at' => $instance->fresh()->last_health_check_at?->toISOString(),
            'instance' => $instance->fresh(['provider']),
        ];
    }

    private function persistResult(WhatsAppInstance $instance, ProviderHealthCheckData $result): void
    {
        $status = match ($result->status) {
            'connected' => InstanceStatus::Connected,
            'disconnected' => InstanceStatus::Disconnected,
            'invalid_credentials' => InstanceStatus::InvalidCredentials,
            default => InstanceStatus::Error,
        };

        $updates = [
            'status' => $status,
            'last_status_sync_at' => now(),
            'last_health_check_at' => now(),
            'last_health_status' => $result->status,
            'last_error' => $result->error ?? ($result->success ? null : $result->message),
            'provider_meta_json' => array_filter([
                ...($instance->provider_meta_json ?? []),
                ...($result->meta ?? []),
            ], fn ($value) => $value !== null),
        ];

        if ($result->phone) {
            $updates['phone_number'] = $result->phone;
        }

        if ($status === InstanceStatus::Connected) {
            $updates['connected_at'] = now();
            $updates['disconnected_at'] = null;
        }

        if ($status === InstanceStatus::Disconnected && $instance->normalizedStatus() === InstanceStatus::Connected) {
            $updates['disconnected_at'] = now();
        }

        $instance->update($updates);
    }
}
