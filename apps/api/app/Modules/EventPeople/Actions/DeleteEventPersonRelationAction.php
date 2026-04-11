<?php

namespace App\Modules\EventPeople\Actions;

use App\Modules\EventPeople\Models\EventPersonRelation;

class DeleteEventPersonRelationAction
{
    public function execute(EventPersonRelation $relation): void
    {
        $relation->delete();
    }
}
