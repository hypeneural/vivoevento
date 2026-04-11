<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonType: string
{
    case Bride = 'bride';
    case Groom = 'groom';
    case Mother = 'mother';
    case Father = 'father';
    case Sibling = 'sibling';
    case Guest = 'guest';
    case Friend = 'friend';
    case Groomsman = 'groomsman';
    case Bridesmaid = 'bridesmaid';
    case Vendor = 'vendor';
    case Staff = 'staff';
    case Speaker = 'speaker';
    case Artist = 'artist';
    case Executive = 'executive';
    case Unknown = 'unknown';
}
