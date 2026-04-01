<?php

namespace App\Modules\Events\Enums;

enum EventType: string
{
    case Wedding = 'wedding';
    case Birthday = 'birthday';
    case Fifteen = 'fifteen';
    case Corporate = 'corporate';
    case Fair = 'fair';
    case Graduation = 'graduation';
    case Other = 'other';
}
