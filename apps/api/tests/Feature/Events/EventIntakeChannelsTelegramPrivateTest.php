<?php

use App\Modules\Billing\Models\Subscription;
use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Plans\Models\Plan;

it('creates an event with telegram private intake configuration when entitlements allow it', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedTelegramPrivateEntitlements($organization, [
        'channels.telegram.enabled' => 'true',
    ]);

    $response = $this->apiPost('/events', [
        'organization_id' => $organization->id,
        'title' => 'Evento Telegram Privado',
        'event_type' => 'wedding',
        'intake_channels' => [
            'telegram' => [
                'enabled' => true,
                'bot_username' => 'eventovivoBot',
                'media_inbox_code' => 'ANAEJOAO',
                'session_ttl_minutes' => 180,
            ],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    $eventId = $response->json('data.id');

    $response->assertJsonPath('data.intake_channels.telegram.enabled', true)
        ->assertJsonPath('data.intake_channels.telegram.bot_username', 'eventovivoBot')
        ->assertJsonPath('data.intake_channels.telegram.media_inbox_code', 'ANAEJOAO')
        ->assertJsonPath('data.intake_channels.telegram.session_ttl_minutes', 180);

    $this->assertDatabaseHas('event_channels', [
        'event_id' => $eventId,
        'channel_type' => ChannelType::TelegramBot->value,
        'provider' => 'telegram',
        'external_id' => 'ANAEJOAO',
        'status' => 'active',
    ]);

    $channel = EventChannel::query()
        ->where('event_id', $eventId)
        ->where('channel_type', ChannelType::TelegramBot->value)
        ->sole();

    expect(data_get($channel->config_json, 'bot_username'))->toBe('eventovivoBot')
        ->and(data_get($channel->config_json, 'media_inbox_code'))->toBe('ANAEJOAO')
        ->and(data_get($channel->config_json, 'session_ttl_minutes'))->toBe(180)
        ->and(data_get($channel->config_json, 'allow_private'))->toBeTrue()
        ->and(data_get($channel->config_json, 'v1_allowed_updates'))->toBe(['message', 'my_chat_member']);
});

it('returns telegram private intake configuration in the event detail payload', function () {
    [$user, $organization] = $this->actingAsOwner();

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => ChannelType::TelegramBot->value,
        'provider' => 'telegram',
        'external_id' => 'ANAEJOAO',
        'label' => 'Telegram',
        'status' => 'active',
        'config_json' => [
            'bot_username' => 'eventovivoBot',
            'media_inbox_code' => 'ANAEJOAO',
            'session_ttl_minutes' => 180,
            'allow_private' => true,
            'v1_allowed_updates' => ['message'],
        ],
    ]);

    $response = $this->apiGet("/events/{$event->id}");

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.intake_channels.telegram.enabled', true)
        ->assertJsonPath('data.intake_channels.telegram.bot_username', 'eventovivoBot')
        ->assertJsonPath('data.intake_channels.telegram.media_inbox_code', 'ANAEJOAO')
        ->assertJsonPath('data.intake_channels.telegram.session_ttl_minutes', 180);
});

it('blocks telegram private intake when the event entitlement does not allow telegram', function () {
    [$user, $organization] = $this->actingAsOwner();

    seedTelegramPrivateEntitlements($organization, [
        'channels.telegram.enabled' => 'false',
    ]);

    $event = Event::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $response = $this->apiPatch("/events/{$event->id}", [
        'intake_channels' => [
            'telegram' => [
                'enabled' => true,
                'bot_username' => 'eventovivoBot',
                'media_inbox_code' => 'ANAEJOAO',
                'session_ttl_minutes' => 180,
            ],
        ],
    ]);

    $this->assertApiValidationError($response, [
        'intake_channels.telegram.enabled',
    ]);
});

function seedTelegramPrivateEntitlements($organization, array $features): void
{
    $plan = Plan::create([
        'code' => fake()->unique()->slug(2),
        'name' => 'Plano telegram privado',
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
