<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppProviderInterface;
use App\Modules\WhatsApp\Clients\Providers\ZApi\ZApiWhatsAppProvider;
use App\Modules\WhatsApp\Exceptions\ProviderNotSupportedException;
use App\Modules\WhatsApp\Models\WhatsAppInstance;

/**
 * Resolves the correct WhatsApp provider adapter for a given instance.
 *
 * Usage:
 *   $provider = $resolver->forInstance($instance);
 *   $provider->sendText($instance, $data);
 */
class WhatsAppProviderResolver
{
    /**
     * Resolve the provider adapter for a given instance.
     */
    public function forInstance(WhatsAppInstance $instance): WhatsAppProviderInterface
    {
        return $this->forProviderKey($instance->provider_key->value);
    }

    /**
     * Resolve the provider adapter by its key.
     */
    public function forProviderKey(string $key): WhatsAppProviderInterface
    {
        return match ($key) {
            'zapi' => app(ZApiWhatsAppProvider::class),
            // 'evolution' => app(EvolutionWhatsAppProvider::class),
            default => throw new ProviderNotSupportedException("WhatsApp provider '{$key}' is not supported."),
        };
    }

    /**
     * Resolve the default provider from config.
     */
    public function forDefaultProvider(): WhatsAppProviderInterface
    {
        $key = config('whatsapp.default_provider', 'zapi');

        return $this->forProviderKey($key);
    }
}
