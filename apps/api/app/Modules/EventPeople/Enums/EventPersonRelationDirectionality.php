<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonRelationDirectionality: string
{
    case Directed = 'directed';
    case Undirected = 'undirected';
}
