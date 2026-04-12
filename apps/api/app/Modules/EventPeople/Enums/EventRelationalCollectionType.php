<?php

namespace App\Modules\EventPeople\Enums;

enum EventRelationalCollectionType: string
{
    case PersonBestOf = 'person_best_of';
    case PairBestOf = 'pair_best_of';
    case GroupBestOf = 'group_best_of';
    case FamilyMoment = 'family_moment';
    case MustHaveDelivery = 'must_have_delivery';
}
