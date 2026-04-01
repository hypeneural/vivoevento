<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Generic provider action result (disconnect, etc.).
 */
final readonly class ProviderActionResultData
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public array $rawResponse = [],
    ) {}
}
