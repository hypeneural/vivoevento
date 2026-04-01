<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for sending a carousel message.
 */
final readonly class SendCarouselData
{
    /**
     * @param string $phone
     * @param string $message  Header text of the carousel
     * @param array  $cards    Array of carousel cards, each with:
     *                         - text: string
     *                         - image: string (URL or base64)
     *                         - buttons?: array of { id?, label, type: URL|CALL|REPLY, url?, phone? }
     * @param int|null $delayMessage
     */
    public function __construct(
        public string $phone,
        public string $message,
        public array $cards,
        public ?int $delayMessage = null,
    ) {}
}
