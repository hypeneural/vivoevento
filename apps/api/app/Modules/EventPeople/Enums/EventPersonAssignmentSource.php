<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonAssignmentSource: string
{
    case AwsMatch = 'aws_match';
    case ClusterSuggestion = 'cluster_suggestion';
    case ManualConfirmed = 'manual_confirmed';
    case ManualCorrected = 'manual_corrected';
    case Imported = 'imported';
}
