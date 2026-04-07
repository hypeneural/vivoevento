<?php

use App\Modules\Billing\Models\Subscription;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventMediaSenderBlacklist;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Plans\Models\Plan;

it('returns blacklist entries and sender summaries in the event detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'blacklist' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $blockingEntry = EventMediaSenderBlacklist::factory()->create([
        'event_id' => $event->id,
        'identity_type' => 'lid',
        'identity_value' => '11111111111111@lid',
        'normalized_phone' => null,
        'reason' => 'Bloqueado pela curadoria',
        'expires_at' => now()->addHours(2),
        'is_active' => true,
    ]);

    $firstSenderMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'msg-ana-1',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_lid' => '11111111111111@lid',
        'sender_name' => 'Ana Martins',
        'sender_avatar_url' => 'https://cdn.eventovivo.test/ana.jpg',
        'body_text' => 'Primeira foto',
        'media_url' => 'https://cdn.eventovivo.test/ana-1.jpg',
        'normalized_payload_json' => [
            '_event_context' => [
                'sender_external_id' => '11111111111111@lid',
                'sender_phone' => '554899991111',
                'sender_lid' => '11111111111111@lid',
                'sender_name' => 'Ana Martins',
                'sender_avatar_url' => 'https://cdn.eventovivo.test/ana.jpg',
            ],
        ],
        'status' => 'processed',
        'received_at' => now()->subMinutes(4),
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $firstSenderMessage->id,
        'source_type' => 'whatsapp_group',
        'source_label' => 'Ana Martins',
    ]);

    InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'msg-ana-2',
        'message_type' => 'text',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_lid' => '11111111111111@lid',
        'sender_name' => 'Ana Martins',
        'sender_avatar_url' => 'https://cdn.eventovivo.test/ana.jpg',
        'body_text' => 'Legenda extra',
        'normalized_payload_json' => [
            '_event_context' => [
                'sender_external_id' => '11111111111111@lid',
                'sender_phone' => '554899991111',
                'sender_lid' => '11111111111111@lid',
                'sender_name' => 'Ana Martins',
            ],
        ],
        'status' => 'processed',
        'received_at' => now()->subMinutes(2),
    ]);

    $secondSenderMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'msg-caio-1',
        'message_type' => 'image',
        'chat_external_id' => '554899992222',
        'sender_external_id' => '554899992222',
        'sender_phone' => '554899992222',
        'sender_name' => 'Caio Souza',
        'body_text' => 'Foto individual',
        'media_url' => 'https://cdn.eventovivo.test/caio-1.jpg',
        'normalized_payload_json' => [
            '_event_context' => [
                'sender_external_id' => '554899992222',
                'sender_phone' => '554899992222',
                'sender_name' => 'Caio Souza',
            ],
        ],
        'status' => 'processed',
        'received_at' => now()->subMinute(),
    ]);

    EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $secondSenderMessage->id,
        'source_type' => 'whatsapp_direct',
        'source_label' => 'Caio Souza',
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    expect($response->json('data.intake_blacklist.enabled'))->toBeTrue()
        ->and($response->json('data.intake_blacklist.entries.0.id'))->toBe($blockingEntry->id)
        ->and($response->json('data.intake_blacklist.entries.0.identity_type'))->toBe('lid')
        ->and($response->json('data.intake_blacklist.entries.0.is_currently_blocking'))->toBeTrue();

    $senders = collect($response->json('data.intake_blacklist.senders'));
    $ana = $senders->firstWhere('sender_external_id', '11111111111111@lid');
    $caio = $senders->firstWhere('sender_external_id', '554899992222');

    expect($senders)->toHaveCount(2)
        ->and($ana)->not->toBeNull()
        ->and($ana['sender_name'])->toBe('Ana Martins')
        ->and($ana['sender_avatar_url'])->toBe('https://cdn.eventovivo.test/ana.jpg')
        ->and($ana['inbound_count'])->toBe(2)
        ->and($ana['media_count'])->toBe(1)
        ->and($ana['blocked'])->toBeTrue()
        ->and($ana['blocking_entry_id'])->toBe($blockingEntry->id)
        ->and($ana['recommended_identity_type'])->toBe('lid')
        ->and($caio)->not->toBeNull()
        ->and($caio['media_count'])->toBe(1)
        ->and($caio['blocked'])->toBeFalse()
        ->and($caio['recommended_identity_type'])->toBe('phone');
});

it('updates blacklist entries through the event crud and deactivates omitted entries', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedBlacklistEntitlements($organization, [
        'channels.blacklist.enabled' => 'true',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'blacklist' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $oldEntry = EventMediaSenderBlacklist::factory()->create([
        'event_id' => $event->id,
        'identity_type' => 'phone',
        'identity_value' => '554899991111',
        'normalized_phone' => '554899991111',
        'is_active' => true,
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'intake_blacklist' => [
            'entries' => [
                [
                    'identity_type' => 'lid',
                    'identity_value' => '11111111111111@lid',
                    'reason' => 'Bloqueio temporario',
                    'expires_at' => now()->addDay()->toISOString(),
                    'is_active' => true,
                ],
            ],
        ],
    ]);

    $this->assertApiSuccess($response);

    $entry = EventMediaSenderBlacklist::query()
        ->where('event_id', $event->id)
        ->where('identity_type', 'lid')
        ->sole();

    expect($entry->reason)->toBe('Bloqueio temporario')
        ->and($entry->is_active)->toBeTrue()
        ->and($oldEntry->fresh()->is_active)->toBeFalse()
        ->and($response->json('data.intake_blacklist.entries.0.identity_type'))->toBe('lid');
});

it('blocks blacklist changes when the event entitlements do not allow this control', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'blacklist' => [
                    'enabled' => false,
                ],
            ],
        ],
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'intake_blacklist' => [
            'entries' => [
                [
                    'identity_type' => 'phone',
                    'identity_value' => '554899991111',
                    'is_active' => true,
                ],
            ],
        ],
    ]);

    $this->assertApiValidationError($response, ['intake_blacklist.entries']);

    expect(EventMediaSenderBlacklist::query()->where('event_id', $event->id)->exists())->toBeFalse();
});

it('supports quick block and unblock actions from the event detail sender list', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedBlacklistEntitlements($organization, [
        'channels.blacklist.enabled' => 'true',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'blacklist' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $blockResponse = $this->apiPost("/events/{$event->id}/intake-blacklist/entries", [
        'identity_type' => 'lid',
        'identity_value' => '11111111111111@lid',
        'reason' => 'Bloqueado pelo detalhe do evento',
        'expires_at' => now()->addWeek()->toISOString(),
        'is_active' => true,
    ]);

    $this->assertApiSuccess($blockResponse);

    $entry = EventMediaSenderBlacklist::query()
        ->where('event_id', $event->id)
        ->where('identity_type', 'lid')
        ->sole();

    $blockResponse->assertJsonPath('data.entry_id', $entry->id)
        ->assertJsonPath('data.intake_blacklist.entries.0.identity_value', '11111111111111@lid')
        ->assertJsonPath('data.intake_blacklist.entries.0.is_currently_blocking', true);

    $unblockResponse = $this->apiDelete("/events/{$event->id}/intake-blacklist/entries/{$entry->id}");

    $this->assertApiSuccess($unblockResponse);

    expect($entry->fresh()->is_active)->toBeFalse();
    $unblockResponse->assertJsonPath('data.entry_id', $entry->id)
        ->assertJsonPath('data.intake_blacklist.entries.0.is_active', false)
        ->assertJsonPath('data.intake_blacklist.entries.0.is_currently_blocking', false);
});

it('keeps sender block state converged between event detail, moderation and gallery surfaces', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedBlacklistEntitlements($organization, [
        'channels.blacklist.enabled' => 'true',
    ]);

    $event = Event::factory()->active()->create([
        'organization_id' => $organization->id,
        'current_entitlements_json' => [
            'channels' => [
                'blacklist' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $inbound = InboundMessage::query()->create([
        'event_id' => $event->id,
        'provider' => 'zapi',
        'message_id' => 'conv-ana-001',
        'message_type' => 'image',
        'chat_external_id' => '120363499999999999-group',
        'sender_external_id' => '11111111111111@lid',
        'sender_phone' => '554899991111',
        'sender_lid' => '11111111111111@lid',
        'sender_name' => 'Ana Martins',
        'sender_avatar_url' => 'https://cdn.eventovivo.test/ana.jpg',
        'status' => 'processed',
        'received_at' => now()->subMinutes(5),
    ]);

    $media = EventMedia::factory()->approved()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inbound->id,
        'publication_status' => PublicationStatus::Hidden->value,
        'source_type' => 'whatsapp_group',
        'source_label' => 'Ana Martins',
    ]);

    $blockResponse = $this->apiPost("/events/{$event->id}/intake-blacklist/entries", [
        'identity_type' => 'lid',
        'identity_value' => '11111111111111@lid',
        'reason' => 'Bloqueio convergente',
        'expires_at' => now()->addHours(12)->toISOString(),
        'is_active' => true,
    ]);

    $this->assertApiSuccess($blockResponse);

    $eventDetailResponse = $this->apiGet("/events/{$event->id}");
    $moderationFeedResponse = $this->apiGet("/media/feed?event_id={$event->id}&search=11111111111111@lid&sender_blocked=1");
    $galleryResponse = $this->apiGet("/gallery?event_id={$event->id}&search=11111111111111@lid");

    $this->assertApiSuccess($eventDetailResponse);
    $moderationFeedResponse->assertOk();
    $this->assertApiPaginated($galleryResponse);

    expect(collect($eventDetailResponse->json('data.intake_blacklist.senders'))->firstWhere('sender_external_id', '11111111111111@lid'))
        ->not->toBeNull()
        ->and(collect($eventDetailResponse->json('data.intake_blacklist.senders'))->firstWhere('sender_external_id', '11111111111111@lid')['blocked'])->toBeTrue()
        ->and($moderationFeedResponse->json('data.0.id'))->toBe($media->id)
        ->and($moderationFeedResponse->json('data.0.sender_blocked'))->toBeTrue()
        ->and($galleryResponse->json('data.0.id'))->toBe($media->id)
        ->and($galleryResponse->json('data.0.sender_blocked'))->toBeTrue();

    $unblockResponse = $this->apiDelete("/media/{$media->id}/sender-block");

    $this->assertApiSuccess($unblockResponse);

    $eventDetailAfterUnblock = $this->apiGet("/events/{$event->id}");
    $moderationAfterUnblock = $this->apiGet("/media/feed?event_id={$event->id}&search=11111111111111@lid");
    $galleryAfterUnblock = $this->apiGet("/gallery?event_id={$event->id}&search=11111111111111@lid");

    $this->assertApiSuccess($eventDetailAfterUnblock);
    $moderationAfterUnblock->assertOk();
    $this->assertApiPaginated($galleryAfterUnblock);

    expect(collect($eventDetailAfterUnblock->json('data.intake_blacklist.senders'))->firstWhere('sender_external_id', '11111111111111@lid'))
        ->not->toBeNull()
        ->and(collect($eventDetailAfterUnblock->json('data.intake_blacklist.senders'))->firstWhere('sender_external_id', '11111111111111@lid')['blocked'])->toBeFalse()
        ->and($moderationAfterUnblock->json('data.0.id'))->toBe($media->id)
        ->and($moderationAfterUnblock->json('data.0.sender_blocked'))->toBeFalse()
        ->and($galleryAfterUnblock->json('data.0.id'))->toBe($media->id)
        ->and($galleryAfterUnblock->json('data.0.sender_blocked'))->toBeFalse();
});

function seedBlacklistEntitlements($organization, array $features): void
{
    $plan = Plan::create([
        'code' => fake()->unique()->slug(2),
        'name' => 'Plano blacklist',
        'audience' => 'b2b',
        'status' => 'active',
    ]);

    foreach ($features as $featureKey => $featureValue) {
        $plan->features()->create([
            'feature_key' => $featureKey,
            'feature_value' => $featureValue,
        ]);
    }

    Subscription::create([
        'organization_id' => $organization->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);
}
