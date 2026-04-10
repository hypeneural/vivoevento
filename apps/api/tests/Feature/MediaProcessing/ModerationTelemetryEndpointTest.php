<?php

use Illuminate\Support\Facades\Log;

it('accepts moderation telemetry events from the authenticated moderation route', function () {
    [$user, $organization] = $this->actingAsOwner();

    config()->set('observability.moderation_log_channel', 'stack');

    Log::shouldReceive('channel')
        ->once()
        ->with('stack')
        ->andReturnSelf();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $payload) use ($organization, $user): bool {
            expect($message)->toBe('moderation.feed.client_telemetry');
            expect($payload)->toMatchArray([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'event' => 'filters_stabilized',
                'duration_ms' => 182,
                'item_count' => 24,
            ]);

            return true;
        });

    $response = $this->apiPost('/media/feed/telemetry', [
        'event' => 'filters_stabilized',
        'duration_ms' => 182,
        'item_count' => 24,
        'filters' => [
            'status' => 'pending_moderation',
            'per_page' => 24,
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true);
});

it('validates moderation telemetry event names', function () {
    $this->actingAsOwner();

    $response = $this->apiPost('/media/feed/telemetry', [
        'event' => 'not-supported',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['event']);
});
