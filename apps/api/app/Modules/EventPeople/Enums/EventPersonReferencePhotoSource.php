<?php

namespace App\Modules\EventPeople\Enums;

enum EventPersonReferencePhotoSource: string
{
    case EventFace = 'event_face';
    case ManualUpload = 'manual_upload';
}
