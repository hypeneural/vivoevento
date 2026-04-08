<?php

use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Wall\Models\EventWallSetting;

it('forbids wall insights access when the event belongs to another organization', function () {
    [$user, $organization] = $this->actingAsManager();

    $otherOrganization = $this->createOrganization();

    $event = Event::factory()->active()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiForbidden($response);
});

it('returns an empty wall insights payload when the event has no media yet', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.topContributor', null)
        ->assertJsonPath('data.totals.received', 0)
        ->assertJsonPath('data.totals.approved', 0)
        ->assertJsonPath('data.totals.queued', 0)
        ->assertJsonPath('data.totals.displayed', null)
        ->assertJsonPath('data.recentItems', [])
        ->assertJsonPath('data.sourceMix', [])
        ->assertJsonPath('data.lastCaptureAt', null);
});

it('returns top contributor totals recent items and source mix for wall insights', function () {
    [$user, $organization] = $this->actingAsManager();

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
    ]);

    EventWallSetting::factory()->live()->create([
        'event_id' => $event->id,
    ]);

    $anaA = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-ana-a',
        'message_type' => 'image',
        'sender_phone' => '5511999990001',
        'sender_name' => 'Ana',
        'status' => 'received',
        'received_at' => now()->subMinutes(10),
    ]);

    $anaB = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-ana-b',
        'message_type' => 'image',
        'sender_phone' => '5511999990001',
        'sender_name' => 'Ana',
        'status' => 'received',
        'received_at' => now()->subMinutes(6),
    ]);

    $bruno = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'whatsapp',
        'message_id' => 'msg-bruno',
        'message_type' => 'image',
        'sender_phone' => '5511999990002',
        'sender_name' => 'Bruno',
        'status' => 'received',
        'received_at' => now()->subMinutes(4),
    ]);

    $anaPublished = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $anaA->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);
    $anaPublished->forceFill([
        'created_at' => now()->subMinutes(9),
        'published_at' => now()->subMinutes(8),
    ])->saveQuietly();
    $anaPublished->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/ana-published.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $anaApproved = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $anaB->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);
    $anaApproved->forceFill([
        'created_at' => now()->subMinutes(5),
    ])->saveQuietly();
    $anaApproved->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/ana-approved.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $brunoPublished = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $bruno->id,
        'source_type' => 'whatsapp',
        'source_label' => 'WhatsApp',
    ]);
    $brunoPublished->forceFill([
        'created_at' => now()->subMinutes(3),
        'published_at' => now()->subMinutes(2),
    ])->saveQuietly();
    $brunoPublished->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/bruno-published.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $uploadMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'media_type' => 'image',
        'source_type' => 'public_upload',
        'source_label' => 'Upload',
        'moderation_status' => 'rejected',
        'publication_status' => 'draft',
    ]);
    $uploadMedia->forceFill([
        'created_at' => now()->subMinute(),
    ])->saveQuietly();
    $uploadMedia->variants()->create([
        'variant_key' => 'thumb',
        'disk' => 'public',
        'path' => 'events/'.$event->id.'/thumbs/upload-rejected.jpg',
        'width' => 320,
        'height' => 320,
        'size_bytes' => 10240,
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->apiGet("/events/{$event->id}/wall/insights");

    $this->assertApiSuccess($response);

    $response
        ->assertJsonPath('data.topContributor.displayName', 'Ana')
        ->assertJsonPath('data.topContributor.mediaCount', 2)
        ->assertJsonPath('data.topContributor.source', 'whatsapp')
        ->assertJsonPath('data.totals.received', 4)
        ->assertJsonPath('data.totals.approved', 3)
        ->assertJsonPath('data.totals.queued', 2)
        ->assertJsonPath('data.totals.displayed', null)
        ->assertJsonPath('data.recentItems.0.id', (string) $uploadMedia->id)
        ->assertJsonPath('data.recentItems.0.senderName', 'Upload')
        ->assertJsonPath('data.recentItems.0.source', 'upload')
        ->assertJsonPath('data.recentItems.0.status', 'error')
        ->assertJsonPath('data.recentItems.1.id', (string) $brunoPublished->id)
        ->assertJsonPath('data.recentItems.1.senderName', 'Bruno')
        ->assertJsonPath('data.recentItems.1.source', 'whatsapp')
        ->assertJsonPath('data.recentItems.1.status', 'queued')
        ->assertJsonPath('data.lastCaptureAt', $uploadMedia->created_at?->toIso8601String());

    $payload = $response->json('data');

    expect($payload['recentItems'][0]['previewUrl'])->toContain('events/'.$event->id.'/thumbs/upload-rejected.jpg')
        ->and(collect($payload['sourceMix'])->firstWhere('source', 'whatsapp')['count'] ?? null)->toBe(3)
        ->and(collect($payload['sourceMix'])->firstWhere('source', 'upload')['count'] ?? null)->toBe(1);
});
