<?php

use App\Modules\Events\Models\Event;

it('treats an active event as active regardless of its scheduled dates', function () {
    $futureEvent = Event::factory()->active()->create([
        'starts_at' => now()->addDays(10),
        'ends_at' => now()->addDays(12),
    ]);

    $expiredEvent = Event::factory()->active()->create([
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDays(1),
    ]);

    expect($futureEvent->isActive())->toBeTrue()
        ->and($expiredEvent->isActive())->toBeTrue();
});
