<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for updating WhatsApp group settings/permissions.
 */
final readonly class UpdateGroupSettingsData
{
    public function __construct(
        public string $groupId,
        public bool $adminOnlyMessage = false,
        public bool $adminOnlySettings = false,
        public bool $requireAdminApproval = false,
        public bool $adminOnlyAddMember = false,
    ) {}
}
