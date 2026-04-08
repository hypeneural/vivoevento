<?php

use Illuminate\Console\Scheduling\Schedule;

it('registers queue monitor commands for the critical production lanes', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) ($event->command ?? ''))
        ->filter()
        ->values();

    expect($commands->contains(fn (string $command) => str_contains($command, 'queue:monitor redis:webhooks --max=25')))->toBeTrue()
        ->and($commands->contains(fn (string $command) => str_contains($command, 'queue:monitor redis:media-variants --max=50')))->toBeTrue()
        ->and($commands->contains(fn (string $command) => str_contains($command, 'queue:monitor redis:media-audit --max=50')))->toBeTrue()
        ->and($commands->contains(fn (string $command) => str_contains($command, 'queue:monitor redis:media-publish --max=25')))->toBeTrue()
        ->and($commands->contains(fn (string $command) => str_contains($command, 'queue:monitor redis:broadcasts --max=50')))->toBeTrue();
});
