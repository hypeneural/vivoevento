<?php

namespace App\Modules\WhatsApp\Exceptions;

use RuntimeException;

class MessageSendFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to send WhatsApp message.',
        public readonly ?int $httpStatus = null,
        public readonly ?array $providerResponse = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
