<?php

namespace App\Modules\WhatsApp\Enums;

enum ChatType: string
{
    case Private = 'private';
    case Group = 'group';
}
