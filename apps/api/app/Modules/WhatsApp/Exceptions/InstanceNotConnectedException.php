<?php

namespace App\Modules\WhatsApp\Exceptions;

use RuntimeException;

class InstanceNotConnectedException extends RuntimeException
{
    public function __construct(string $message = 'WhatsApp instance is not connected.')
    {
        parent::__construct($message);
    }
}
