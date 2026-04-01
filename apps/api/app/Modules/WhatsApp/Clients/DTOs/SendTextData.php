<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for sending a text message.
 */
final readonly class SendTextData
{
    /**
     * @param string      $phone
     * @param string      $message
     * @param array|null  $mentioned     Array of phone numbers to mention in groups
     * @param int|null    $delayMessage
     * @param int|null    $delayTyping
     * @param string|null $editMessageId
     */
    public function __construct(
        public string $phone,
        public string $message,
        public ?array $mentioned = null,
        public ?int $delayMessage = null,
        public ?int $delayTyping = null,
        public ?string $editMessageId = null,
    ) {}

    public function hasMentions(): bool
    {
        return ! empty($this->mentioned);
    }
}
