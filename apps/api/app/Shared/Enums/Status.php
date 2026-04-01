<?php

namespace App\Shared\Enums;

/**
 * Global status enum used across multiple modules.
 */
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Deleted = 'deleted';
}
