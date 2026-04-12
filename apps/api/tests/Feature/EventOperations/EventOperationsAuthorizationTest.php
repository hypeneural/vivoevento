<?php

use App\Modules\Events\Models\Event;

it('requires authentication for the room and timeline endpoints', function () {
    $event = Event::factory()->active()->create();

    $this->assertApiUnauthorized($this->apiGet("/events/{$event->id}/operations/room"));
    $this->assertApiUnauthorized($this->apiGet("/events/{$event->id}/operations/timeline"));
});

it('forbids same-organization viewers without operations view permission', function () {
    [$user, $organization] = $this->actingAsViewer();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    $this->assertApiForbidden($this->apiGet("/events/{$event->id}/operations/room"));
    $this->assertApiForbidden($this->apiGet("/events/{$event->id}/operations/timeline"));
});

it('forbids operators from another organization even when they hold operations view', function () {
    [$user, $organization] = $this->actingAsOwner();

    $foreignEvent = Event::factory()->active()->create([
        'organization_id' => $this->createOrganization()->id,
    ]);

    $this->assertApiForbidden($this->apiGet("/events/{$foreignEvent->id}/operations/room"));
    $this->assertApiForbidden($this->apiGet("/events/{$foreignEvent->id}/operations/timeline"));
});
