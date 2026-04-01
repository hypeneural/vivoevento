<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Result after creating a WhatsApp group.
 */
final readonly class ProviderGroupCreatedData
{
    public function __construct(
        public bool $success,
        public ?string $groupId = null,
        public ?string $invitationLink = null,
        public ?string $error = null,
        public array $rawResponse = [],
    ) {}
}
