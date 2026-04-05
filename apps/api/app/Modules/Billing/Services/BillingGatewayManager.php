<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingOrderMode;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class BillingGatewayManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function default(): BillingGatewayInterface
    {
        return $this->forProvider(config('billing.gateways.default', 'manual'));
    }

    public function forMode(BillingOrderMode|string $mode): BillingGatewayInterface
    {
        $modeValue = $mode instanceof BillingOrderMode ? $mode->value : $mode;

        $provider = match ($modeValue) {
            BillingOrderMode::Subscription->value => config('billing.gateways.subscription', config('billing.gateways.default', 'manual')),
            BillingOrderMode::EventPackage->value => config('billing.gateways.event_package', config('billing.gateways.default', 'manual')),
            default => config('billing.gateways.default', 'manual'),
        };

        return $this->forProvider($provider);
    }

    public function forProvider(?string $provider): BillingGatewayInterface
    {
        $providerKey = $provider ?: config('billing.gateways.default', 'manual');
        $providers = config('billing.gateways.providers', []);
        $class = $providers[$providerKey] ?? null;

        if (! is_string($class) || $class === '') {
            throw new InvalidArgumentException("Billing gateway provider [{$providerKey}] is not configured.");
        }

        $instance = $this->container->make($class);

        if (! $instance instanceof BillingGatewayInterface) {
            throw new InvalidArgumentException("Billing gateway provider [{$providerKey}] must implement BillingGatewayInterface.");
        }

        return $instance;
    }
}
