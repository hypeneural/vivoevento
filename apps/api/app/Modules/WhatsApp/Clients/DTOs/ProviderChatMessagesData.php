<?php

namespace App\Modules\WhatsApp\Clients\DTOs;

final readonly class ProviderChatMessagesData
{
    public function __construct(
        public bool $success,
        public array $messages = [],
        public ?string $remoteJid = null,
        public ?string $error = null,
    ) {}
}
