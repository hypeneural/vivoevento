<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

final readonly class ProviderConnectionDetailsData
{
    public function __construct(
        public ?string $phone = null,
        public ?string $statusMessage = null,
        public array $profile = [],
        public array $device = [],
        public array $meta = [],
        public array $rawResponse = [],
        public ?string $error = null,
    ) {}
}
