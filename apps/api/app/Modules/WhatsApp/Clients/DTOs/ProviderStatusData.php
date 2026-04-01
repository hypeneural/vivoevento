<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Standardized provider connection status.
 */
final readonly class ProviderStatusData
{
    public function __construct(
        public bool $connected,
        public bool $smartphoneConnected,
        public ?string $error = null,
        public array $rawResponse = [],
    ) {}

    public function isFullyConnected(): bool
    {
        return $this->connected && $this->smartphoneConnected;
    }
}
