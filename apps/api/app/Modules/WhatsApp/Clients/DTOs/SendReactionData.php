<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for sending a reaction to a message.
 */
final readonly class SendReactionData
{
    public function __construct(
        public string $phone,
        public string $reaction,
        public string $messageId,
        public ?int $delayMessage = null,
    ) {}
}
