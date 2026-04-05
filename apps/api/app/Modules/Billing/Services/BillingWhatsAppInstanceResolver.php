<?php

namespace App\Modules\Billing\Services;

use App\Modules\WhatsApp\Models\WhatsAppInstance;

class BillingWhatsAppInstanceResolver
{
    public function resolve(?int $configuredInstanceId = null, bool $allowSingleConnectedFallback = true): ?WhatsAppInstance
    {
        if ($configuredInstanceId) {
            $instance = WhatsAppInstance::query()->find($configuredInstanceId);

            if (! $instance || ! $instance->is_active || ! $instance->isConnected()) {
                return null;
            }

            return $instance;
        }

        $defaultInstance = WhatsAppInstance::query()
            ->connected()
            ->active()
            ->default()
            ->first();

        if ($defaultInstance) {
            return $defaultInstance;
        }

        if (! $allowSingleConnectedFallback) {
            return null;
        }

        $connectedInstances = WhatsAppInstance::query()
            ->connected()
            ->active()
            ->limit(2)
            ->get();

        return $connectedInstances->count() === 1
            ? $connectedInstances->first()
            : null;
    }
}
