<?php

use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Clients\Providers\ZApi\ZApiWhatsAppProvider;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('sends threaded replies through the z-api send-text endpoint using messageId', function () {
    $instance = WhatsAppInstance::factory()->connected()->create([
        'external_instance_id' => 'INSTANCE-INTAKE-001',
        'provider_token' => 'TOKEN-INTAKE-001',
        'provider_client_token' => 'CLIENT-TOKEN-001',
    ]);

    $provider = app(ZApiWhatsAppProvider::class);
    $instance->refresh();

    Http::fake([
        'https://api.z-api.io/*' => Http::response([
            'messageId' => '3EB0999999999999999999',
            'zaapId' => 'ZAAP-001',
            'id' => '3EB0999999999999999999',
        ], 200),
    ]);

    $result = $provider->sendText($instance, new SendTextData(
        phone: '5548996553954',
        message: '*EventoVivo*',
        messageId: '3EB0689AF3EAE352EC526D',
        privateAnswer: false,
    ));

    expect($result->success)->toBeTrue()
        ->and($result->providerMessageId)->toBe('3EB0999999999999999999')
        ->and($result->providerZaapId)->toBe('ZAAP-001');

    Http::assertSentCount(1);

    /** @var HttpRequest $request */
    $request = collect(Http::recorded())->first()[0];
    $payload = $request->data();

    expect($request->method())->toBe('POST')
        ->and($request->url())->toContain("/instances/{$instance->external_instance_id}/token/{$instance->provider_token}/send-text")
        ->and($request->hasHeader('Client-Token', $instance->provider_client_token))->toBeTrue()
        ->and($payload['phone'] ?? null)->toBe('5548996553954')
        ->and($payload['message'] ?? null)->toBe('*EventoVivo*')
        ->and($payload['messageId'] ?? null)->toBe('3EB0689AF3EAE352EC526D')
        ->and(array_key_exists('privateAnswer', $payload))->toBeTrue()
        ->and($payload['privateAnswer'])->toBeFalse();
});
