<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonRelationType: string
{
    case SpouseOf = 'spouse_of';
    case MotherOf = 'mother_of';
    case FatherOf = 'father_of';
    case SiblingOf = 'sibling_of';
    case ChildOf = 'child_of';
    case GodparentOf = 'godparent_of';
    case FriendOf = 'friend_of';
    case VendorOfEvent = 'vendor_of_event';
    case CeremonialistOfEvent = 'ceremonialist_of_event';
    case PhotographerOfEvent = 'photographer_of_event';
    case ManagerOf = 'manager_of';
    case TeammateOf = 'teammate_of';
    case WorksWith = 'works_with';
    case SpeakerWith = 'speaker_with';
    case SponsorOf = 'sponsor_of';
    case ArtistOfEvent = 'artist_of_event';
    case BackstageWith = 'backstage_with';
}
