<?php

namespace App\Modules\WhatsApp\Exceptions;

use RuntimeException;

class ProviderNotSupportedException extends RuntimeException
{
    public function __construct(string $message = 'WhatsApp provider is not supported.')
    {
        parent::__construct($message);
    }
}
