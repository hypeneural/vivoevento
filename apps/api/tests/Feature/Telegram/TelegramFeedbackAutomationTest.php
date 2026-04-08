<?php

use App\Modules\Channels\Enums\ChannelType;
use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\Events\Models\EventModule;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaIntelligence\Models\EventMediaIntelligenceSetting;
use App\Modules\MediaIntelligence\Models\EventMediaVlmEvaluation;
use App\Modules\MediaProcessing\Enums\PublicationStatus;
use App\Modules\MediaProcessing\Events\MediaPublished;
use App\Modules\MediaProcessing\Events\MediaRejected;
use App\Modules\MediaProcessing\Models\EventMedia;
use App\Modules\Telegram\Listeners\SendTelegramFeedbackOnMediaPublished;
use App\Modules\Telegram\Listeners\SendTelegramFeedbackOnMediaRejected;
use App\Modules\Telegram\Models\TelegramMessageFeedback;
use App\Modules\Telegram\Services\TelegramFeedbackAutomationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.telegram', [
        'base_url' => 'https://api.telegram.org',
        'bot_token' => 'test-telegram-token',
        'webhook_secret_token' => 'secret-telegram-webhook',
        'timeout' => 15,
        'connect_timeout' => 5,
    ]);
});

it('sends detected telegram feedback with chat action and reaction only once', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();
    $context = data_get($inboundMessage->normalized_payload_json, '_event_context');

    app(TelegramFeedbackAutomationService::class)->sendDetectedFeedback($event, $context, $inboundMessage);
    app(TelegramFeedbackAutomationService::class)->sendDetectedFeedback($event, $context, $inboundMessage);

    $feedback = TelegramMessageFeedback::query()->orderBy('id')->get();

    expect($feedback)->toHaveCount(2)
        ->and($feedback[0]->event_channel_id)->toBe($channel->id)
        ->and($feedback[0]->feedback_kind)->toBe('chat_action')
        ->and($feedback[0]->feedback_phase)->toBe('detected')
        ->and($feedback[0]->chat_action)->toBe('upload_photo')
        ->and($feedback[0]->status)->toBe('sent')
        ->and($feedback[1]->feedback_kind)->toBe('reaction')
        ->and($feedback[1]->feedback_phase)->toBe('detected')
        ->and($feedback[1]->reaction_emoji)->toBe("\u{1F44D}")
        ->and($feedback[1]->status)->toBe('sent');

    /* expect(data_get($feedback[1]->resolution_json, 'mode'))->toBe('ai')
        ->and(data_get($feedback[1]->resolution_json, 'source'))->toBe('vlm')
        ->and(data_get($feedback[1]->resolution_json, 'reply_text'))->toBe($feedback[1]->reply_text)
        ->and(data_get($feedback[1]->resolution_json, 'evaluation_id'))->toBe($evaluation->id); */

    /* expect($feedback[1]->resolution_json)->toMatchArray([
        'mode' => 'ai',
        'source' => 'vlm',
        'reply_text' => 'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
        'evaluation_id' => $evaluation->id,
    ]); */

    Http::assertSentCount(2);
    assertTelegramRequestSent('/sendChatAction', [
        'chat_id' => '9007199254740991',
        'action' => 'upload_photo',
    ]);
    assertTelegramReactionSent("\u{1F44D}");
});

it('sends session activation telegram feedback as a threaded message reply', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();
    $context = data_get($inboundMessage->normalized_payload_json, '_event_context');

    app(TelegramFeedbackAutomationService::class)->sendSessionActivatedFeedback($event, $context);
    app(TelegramFeedbackAutomationService::class)->sendSessionActivatedFeedback($event, $context);

    $feedback = TelegramMessageFeedback::query()->sole();

    expect($feedback->event_channel_id)->toBe($channel->id)
        ->and($feedback->feedback_kind)->toBe('reply')
        ->and($feedback->feedback_phase)->toBe('session_activated')
        ->and($feedback->reply_text)->toContain('Sessao ativada')
        ->and($feedback->reply_text)->toContain($event->title)
        ->and($feedback->status)->toBe('sent');

    Http::assertSentCount(1);
    assertTelegramRequestSent('/sendMessage', [
        'chat_id' => '9007199254740991',
        'reply_parameters' => [
            'message_id' => 81,
        ],
    ]);
});

it('sends session closed telegram feedback as a threaded message reply with the event title', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();
    $context = data_get($inboundMessage->normalized_payload_json, '_event_context');

    app(TelegramFeedbackAutomationService::class)->sendSessionClosedFeedback($event, $context);
    app(TelegramFeedbackAutomationService::class)->sendSessionClosedFeedback($event, $context);

    $feedback = TelegramMessageFeedback::query()->sole();

    expect($feedback->event_channel_id)->toBe($channel->id)
        ->and($feedback->feedback_kind)->toBe('reply')
        ->and($feedback->feedback_phase)->toBe('session_closed')
        ->and($feedback->reply_text)->toContain('Sessao encerrada')
        ->and($feedback->reply_text)->toContain($event->title)
        ->and($feedback->status)->toBe('sent');

    Http::assertSentCount(1);
    assertTelegramRequestSent('/sendMessage', [
        'chat_id' => '9007199254740991',
        'reply_parameters' => [
            'message_id' => 81,
        ],
    ]);
});

it('sends blocked telegram feedback as reaction plus threaded message reply with the event title', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();
    $context = data_get($inboundMessage->normalized_payload_json, '_event_context');

    app(TelegramFeedbackAutomationService::class)->sendBlockedFeedback($event, $context);
    app(TelegramFeedbackAutomationService::class)->sendBlockedFeedback($event, $context);

    $feedback = TelegramMessageFeedback::query()->orderBy('id')->get();

    expect($feedback)->toHaveCount(2)
        ->and($feedback[0]->event_channel_id)->toBe($channel->id)
        ->and($feedback[0]->feedback_kind)->toBe('reaction')
        ->and($feedback[0]->feedback_phase)->toBe('blocked')
        ->and($feedback[0]->reaction_emoji)->toBe("\u{1F6AB}")
        ->and($feedback[1]->feedback_kind)->toBe('reply')
        ->and($feedback[1]->feedback_phase)->toBe('blocked')
        ->and($feedback[1]->reply_text)->toContain($event->title)
        ->and($feedback[1]->status)->toBe('sent');

    Http::assertSentCount(2);
    assertTelegramReactionSent("\u{1F6AB}");
    assertTelegramRequestSent('/sendMessage', [
        'chat_id' => '9007199254740991',
        'reply_parameters' => [
            'message_id' => 81,
        ],
    ]);
});

it('sends published telegram feedback as a single reaction from the media event', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();
    $eventMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'source_type' => 'telegram',
        'publication_status' => PublicationStatus::Published->value,
    ]);

    app(SendTelegramFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));
    app(SendTelegramFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));

    $feedback = TelegramMessageFeedback::query()->sole();

    expect($feedback->event_channel_id)->toBe($channel->id)
        ->and($feedback->event_media_id)->toBe($eventMedia->id)
        ->and($feedback->inbound_message_id)->toBe($inboundMessage->id)
        ->and($feedback->feedback_kind)->toBe('reaction')
        ->and($feedback->feedback_phase)->toBe('published')
        ->and($feedback->reaction_emoji)->toBe("\u{2764}\u{FE0F}")
        ->and($feedback->status)->toBe('sent');

    Http::assertSentCount(1);
    assertTelegramReactionSent("\u{2764}\u{FE0F}");
});

it('sends published telegram feedback as reaction plus ai reply when reply_text is available', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();

    EventMediaIntelligenceSetting::factory()->create([
        'event_id' => $event->id,
        'enabled' => true,
        'reply_text_enabled' => true,
    ]);

    $eventMedia = EventMedia::factory()->published()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'source_type' => 'telegram',
        'publication_status' => PublicationStatus::Published->value,
    ]);

    $evaluation = EventMediaVlmEvaluation::factory()->create([
        'event_id' => $event->id,
        'event_media_id' => $eventMedia->id,
        'reply_text' => 'Momento de risadas e lembrancas! 📱🎉',
    ]);

    app(SendTelegramFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));
    app(SendTelegramFeedbackOnMediaPublished::class)->handle(MediaPublished::fromMedia($eventMedia));

    $feedback = TelegramMessageFeedback::query()->orderBy('id')->get();

    expect($feedback)->toHaveCount(2)
        ->and($feedback[0]->feedback_kind)->toBe('reaction')
        ->and($feedback[0]->feedback_phase)->toBe('published')
        ->and($feedback[1]->feedback_kind)->toBe('reply')
        ->and($feedback[1]->feedback_phase)->toBe('published')
        ->and($feedback[1]->trace_id)->toBe('trace-telegram-feedback-001')
        ->and($feedback[1]->reply_text)->toBe('Momento de risadas e lembrancas! 📱🎉');

    /* expect($feedback[1]->resolution_json)->toMatchArray([
        'mode' => 'ai',
        'source' => 'vlm',
        'reply_text' => 'Momento de risadas e lembrancas! ðŸ“±ðŸŽ‰',
        'evaluation_id' => $evaluation->id,
    ]); */

    Http::assertSentCount(2);
    assertTelegramReactionSent("\u{2764}\u{FE0F}");
    assertTelegramRequestSent('/sendMessage', [
        'chat_id' => '9007199254740991',
        'reply_parameters' => [
            'message_id' => 81,
        ],
    ]);
});

it('sends rejected telegram feedback as reaction plus threaded message reply', function () {
    Http::preventStrayRequests();
    fakeTelegramFeedbackApi();

    [$event, $channel, $inboundMessage] = createTelegramFeedbackInboundMessage();
    $eventMedia = EventMedia::factory()->create([
        'event_id' => $event->id,
        'inbound_message_id' => $inboundMessage->id,
        'source_type' => 'telegram',
    ]);

    app(SendTelegramFeedbackOnMediaRejected::class)->handle(MediaRejected::fromMedia($eventMedia));
    app(SendTelegramFeedbackOnMediaRejected::class)->handle(MediaRejected::fromMedia($eventMedia));

    $feedback = TelegramMessageFeedback::query()->orderBy('id')->get();

    expect($feedback)->toHaveCount(2)
        ->and($feedback[0]->event_channel_id)->toBe($channel->id)
        ->and($feedback[0]->event_media_id)->toBe($eventMedia->id)
        ->and($feedback[0]->feedback_kind)->toBe('reaction')
        ->and($feedback[0]->feedback_phase)->toBe('rejected')
        ->and($feedback[0]->reaction_emoji)->toBe("\u{1F6AB}")
        ->and($feedback[1]->feedback_kind)->toBe('reply')
        ->and($feedback[1]->feedback_phase)->toBe('rejected')
        ->and($feedback[1]->reply_text)->toContain('diretrizes do evento')
        ->and($feedback[1]->status)->toBe('sent');

    Http::assertSentCount(2);
    assertTelegramReactionSent("\u{1F6AB}");
    assertTelegramRequestSent('/sendMessage', [
        'chat_id' => '9007199254740991',
        'reply_parameters' => [
            'message_id' => 81,
        ],
    ]);
});

function createTelegramFeedbackInboundMessage(): array
{
    $event = Event::factory()->active()->create([
        'title' => 'Evento Smoke Telegram',
        'current_entitlements_json' => [
            'channels' => [
                'telegram' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    EventModule::query()->create([
        'event_id' => $event->id,
        'module_key' => 'live',
        'is_enabled' => true,
    ]);

    $channel = EventChannel::query()->create([
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

    $inboundMessage = InboundMessage::query()->create([
        'event_id' => $event->id,
        'event_channel_id' => $channel->id,
        'provider' => 'telegram',
        'message_id' => '81',
        'message_type' => 'photo',
        'chat_external_id' => '9007199254740991',
        'sender_external_id' => '9007199254740991',
        'sender_name' => 'Ana',
        'body_text' => 'Evento ao vivo',
        'media_url' => null,
        'normalized_payload_json' => [
            'message_type' => 'photo',
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'trace_id' => 'trace-telegram-feedback-001',
                'intake_source' => 'telegram',
                'source_subtype' => 'direct',
                'provider_message_id' => '81',
                'chat_external_id' => '9007199254740991',
                'sender_external_id' => '9007199254740991',
            ],
        ],
        'status' => 'normalized',
        'received_at' => now(),
    ]);

    return [$event, $channel, $inboundMessage];
}

function fakeTelegramFeedbackApi(): void
{
    Http::fake([
        'https://api.telegram.org/bottest-telegram-token/sendChatAction' => Http::response(['ok' => true, 'result' => true], 200),
        'https://api.telegram.org/bottest-telegram-token/setMessageReaction' => Http::response(['ok' => true, 'result' => true], 200),
        'https://api.telegram.org/bottest-telegram-token/sendMessage' => Http::response([
            'ok' => true,
            'result' => [
                'message_id' => 9001,
            ],
        ], 200),
    ]);
}

function assertTelegramRequestSent(string $path, array $expectedPayload): void
{
    Http::assertSent(function (Request $request) use ($path, $expectedPayload) {
        if ($request->url() !== "https://api.telegram.org/bottest-telegram-token{$path}") {
            return false;
        }

        foreach ($expectedPayload as $key => $value) {
            if (($request->data()[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    });
}

function assertTelegramReactionSent(string $emoji): void
{
    Http::assertSent(function (Request $request) use ($emoji) {
        return $request->url() === 'https://api.telegram.org/bottest-telegram-token/setMessageReaction'
            && ($request->data()['chat_id'] ?? null) === '9007199254740991'
            && ($request->data()['message_id'] ?? null) === 81
            && data_get($request->data(), 'reaction.0.emoji') === $emoji;
    });
}
