<?php

use App\Modules\Channels\Models\EventChannel;
use App\Modules\Events\Models\Event;
use App\Modules\InboundMedia\Jobs\ProcessInboundWebhookJob;
use App\Modules\InboundMedia\Models\ChannelWebhookLog;
use App\Modules\InboundMedia\Models\InboundMessage;
use App\Modules\MediaProcessing\Jobs\GenerateMediaVariantsJob;
use App\Modules\MediaProcessing\Models\EventMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function loadInboundMediaFixture(string $name, array $overrides = []): array
{
    $payload = json_decode(
        file_get_contents(base_path("tests/Fixtures/WhatsApp/ZApi/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    return array_replace_recursive($payload, $overrides);
}

it('consumes _event_context in the canonical inbound media pipeline and creates inbound_message plus event_media', function () {
    Storage::fake('public');
    Queue::fake([GenerateMediaVariantsJob::class]);

    Http::fake([
        'https://cdn.fixture.test/*' => Http::response(
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8QDw8PDw8PDw8PDw8QDxAQFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAgMBIgACEQEDEQH/xAAXAAEBAQEAAAAAAAAAAAAAAAAAAQID/8QAFhEBAQEAAAAAAAAAAAAAAAAAABEB/9oADAMBAAIQAxAAAAH2gD//xAAZEAEAAwEBAAAAAAAAAAAAAAABAAIREiH/2gAIAQEAAQUCk2x1K//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8BP//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8BP//Z'),
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $event = Event::factory()->active()->create();
    $channel = EventChannel::query()->create([
        'event_id' => $event->id,
        'channel_type' => 'whatsapp_group',
        'provider' => 'zapi',
        'external_id' => 'GRUPOAUTO',
        'label' => 'WhatsApp grupos',
        'status' => 'active',
        'config_json' => [],
    ]);

    $payload = array_replace_recursive(
        loadInboundMediaFixture('group-image-with-caption'),
        [
            '_event_context' => [
                'event_id' => $event->id,
                'event_channel_id' => $channel->id,
                'intake_source' => 'whatsapp_group',
                'provider_message_id' => '2A20028071DA23E04188',
                'chat_external_id' => '120363499999999999-group',
                'group_external_id' => '120363499999999999-group',
                'sender_external_id' => '11111111111111@lid',
                'sender_phone' => '554899991111',
                'sender_lid' => '11111111111111@lid',
                'sender_name' => 'Participante Fixture',
                'caption' => 'Teste de grupo',
                'media_url' => 'https://cdn.fixture.test/zapi/group-image-with-caption.jpg',
            ],
        ],
    );

    app(ProcessInboundWebhookJob::class, [
        'provider' => 'zapi',
        'payload' => $payload,
    ])->handle();

    $webhookLog = ChannelWebhookLog::query()->sole();
    $inboundMessage = InboundMessage::query()->sole();
    $eventMedia = EventMedia::query()->sole();

    expect($webhookLog->event_channel_id)->toBe($channel->id)
        ->and($webhookLog->routing_status)->toBe('processed')
        ->and($webhookLog->inbound_message_id)->toBe($inboundMessage->id);

    expect($inboundMessage->event_id)->toBe($event->id)
        ->and($inboundMessage->event_channel_id)->toBe($channel->id)
        ->and($inboundMessage->provider)->toBe('zapi')
        ->and($inboundMessage->message_id)->toBe('2A20028071DA23E04188')
        ->and($inboundMessage->message_type)->toBe('image')
        ->and($inboundMessage->sender_phone)->toBe('554899991111')
        ->and($inboundMessage->sender_name)->toBe('Participante Fixture')
        ->and($inboundMessage->body_text)->toBe('Teste de grupo')
        ->and($inboundMessage->media_url)->toBe('https://cdn.fixture.test/zapi/group-image-with-caption.jpg')
        ->and($inboundMessage->status)->toBe('processed')
        ->and($inboundMessage->processed_at)->not->toBeNull();

    expect($eventMedia->event_id)->toBe($event->id)
        ->and($eventMedia->inbound_message_id)->toBe($inboundMessage->id)
        ->and($eventMedia->source_type)->toBe('whatsapp_group')
        ->and($eventMedia->source_label)->toBe('Participante Fixture')
        ->and($eventMedia->caption)->toBe('Teste de grupo')
        ->and($eventMedia->original_disk)->toBe('public')
        ->and($eventMedia->original_path)->toContain("events/{$event->id}/originals/")
        ->and($eventMedia->processing_status->value)->toBe('downloaded');

    Storage::disk('public')->assertExists($eventMedia->original_path);
    Queue::assertPushed(GenerateMediaVariantsJob::class, fn (GenerateMediaVariantsJob $job) => $job->eventMediaId === $eventMedia->id);
});
