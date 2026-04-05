<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for removing a reaction from a message.
 */
final readonly class RemoveReactionData
{
    public function __construct(
        public string $phone,
        public string $messageId,
        public ?int $delayMessage = null,
        public bool $fromMe = false,
    ) {}
}
