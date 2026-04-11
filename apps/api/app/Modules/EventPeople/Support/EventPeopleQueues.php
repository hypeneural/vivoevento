<?php

namespace App\Modules\EventPeople\Support;

final class EventPeopleQueues
{
    public const HIGH = 'event-people-high';
    public const MEDIUM = 'event-people-medium';
    public const LOW = 'event-people-low';

    public static function high(): string
    {
        return (string) config('event_people.queues.high', self::HIGH);
    }

    public static function medium(): string
    {
        return (string) config('event_people.queues.medium', self::MEDIUM);
    }

    public static function low(): string
    {
        return (string) config('event_people.queues.low', self::LOW);
    }
}
