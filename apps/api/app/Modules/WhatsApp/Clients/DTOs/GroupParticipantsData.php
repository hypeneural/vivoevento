<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for adding/removing participants or promoting admins in a group.
 */
final readonly class GroupParticipantsData
{
    /**
     * @param string   $groupId    Group external ID
     * @param string[] $phones     Array of participant phone numbers
     * @param bool     $autoInvite Send invite link privately if cannot add (add only)
     */
    public function __construct(
        public string $groupId,
        public array $phones,
        public bool $autoInvite = true,
    ) {}
}
