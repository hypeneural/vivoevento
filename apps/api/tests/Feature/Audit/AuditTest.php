<?php

use App\Modules\Events\Models\Event;

// ─── Audit Logs ──────────────────────────────────────────

it('returns audit logs with pagination', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/audit-logs');

    $this->assertApiSuccess($response);
});

// ─── Event Timeline ──────────────────────────────────────

it('returns timeline for an event', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/timeline");

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray();
});

// ─── User Activity ───────────────────────────────────────

it('returns current user activity', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiGet('/users/me/activity');

    $this->assertApiSuccess($response);
    expect($response->json('data'))->toBeArray();
});

// ─── Auth ────────────────────────────────────────────────

it('rejects audit access for unauthenticated user', function () {
    $response = $this->apiGet('/audit-logs');

    $this->assertApiUnauthorized($response);
});
