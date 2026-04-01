<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for updating group properties (name, photo, description).
 */
final readonly class UpdateGroupData
{
    public function __construct(
        public string $groupId,
        public ?string $groupName = null,
        public ?string $groupPhoto = null,
        public ?string $groupDescription = null,
    ) {}
}
