<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonAssignmentStatus: string
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
