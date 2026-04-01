<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Standardized send message result from provider.
 */
final readonly class ProviderSendMessageResultData
{
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public ?string $providerZaapId = null,
        public ?string $error = null,
        public ?int $httpStatus = null,
        public array $rawResponse = [],
    ) {}
}
