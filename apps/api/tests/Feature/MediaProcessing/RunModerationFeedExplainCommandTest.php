<?php

it('fails gracefully outside PostgreSQL when running the moderation explain command', function () {
    $this->artisan('media:moderation-feed-explain')
        ->expectsOutputToContain('exige PostgreSQL real')
        ->assertExitCode(1);
});
