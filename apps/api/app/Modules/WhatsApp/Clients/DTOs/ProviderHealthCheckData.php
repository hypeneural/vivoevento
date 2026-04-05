<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

final readonly class ProviderHealthCheckData
{
    public function __construct(
        public bool $success,
        public bool $connected = false,
        public string $status = 'error',
        public ?string $message = null,
        public ?string $phone = null,
        public array $meta = [],
        public array $rawResponse = [],
        public ?string $error = null,
    ) {}
}
