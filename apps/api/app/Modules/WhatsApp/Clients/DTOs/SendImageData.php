<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for sending an image message.
 */
final readonly class SendImageData
{
    public function __construct(
        public string $phone,
        public string $image,
        public ?string $caption = null,
        public ?string $messageId = null,
        public ?int $delayMessage = null,
        public bool $viewOnce = false,
    ) {}
}
