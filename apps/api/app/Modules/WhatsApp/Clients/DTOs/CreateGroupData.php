<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for creating a WhatsApp group.
 */
final readonly class CreateGroupData
{
    /**
     * @param string   $groupName  Group name
     * @param string[] $phones     Array of participant phone numbers
     * @param bool     $autoInvite Send invite link privately if contact not saved
     */
    public function __construct(
        public string $groupName,
        public array $phones,
        public bool $autoInvite = true,
    ) {}
}
