<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonReviewQueueType: string
{
    case UnknownPerson = 'unknown_person';
    case ClusterSuggestion = 'cluster_suggestion';
    case IdentityConflict = 'identity_conflict';
    case CoverageGap = 'coverage_gap';
}
