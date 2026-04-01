<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for modifying chat state (pin/unpin).
 */
final readonly class ModifyChatData
{
    /**
     * @param string $phone  Phone/chat ID
     * @param string $action 'pin' or 'unpin'
     */
    public function __construct(
        public string $phone,
        public string $action,
    ) {}
}
