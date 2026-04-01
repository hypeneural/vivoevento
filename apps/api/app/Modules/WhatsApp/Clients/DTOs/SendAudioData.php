<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

/**
 * Data for sending an audio message.
 */
final readonly class SendAudioData
{
    public function __construct(
        public string $phone,
        public string $audio,
        public ?int $delayMessage = null,
        public ?int $delayTyping = null,
        public bool $viewOnce = false,
        public bool $waveform = true,
    ) {}
}
