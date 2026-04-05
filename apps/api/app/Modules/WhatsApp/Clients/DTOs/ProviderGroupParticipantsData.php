<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

final readonly class ProviderGroupParticipantsData
{
    public function __construct(
        public bool $success,
        public array $participants = [],
        public ?string $groupId = null,
        public ?string $error = null,
    ) {}
}
