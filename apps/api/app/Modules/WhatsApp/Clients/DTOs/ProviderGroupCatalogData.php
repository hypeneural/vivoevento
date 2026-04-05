<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

final readonly class ProviderGroupCatalogData
{
    public function __construct(
        public bool $success,
        public array $groups = [],
        public bool $includesParticipants = false,
        public ?string $error = null,
    ) {}
}
