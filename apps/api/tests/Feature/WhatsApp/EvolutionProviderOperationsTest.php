<?php

use App\Modules\WhatsApp\Clients\DTOs\CreateGroupData;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Clients\DTOs\UpdateGroupSettingsData;
use App\Modules\WhatsApp\Clients\Providers\Evolution\EvolutionWhatsAppProvider;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use Illuminate\Support\Facades\Http;

it('sends text messages through the evolution adapter', function () {
    $instance = WhatsAppInstance::factory()->evolution()->create();
    $provider = app(EvolutionWhatsAppProvider::class);

    Http::fake([
        'https://evolution.example.com/message/sendText/*' => Http::response([
            'key' => [
                'id' => 'EVOLUTION-MSG-1',
                'remoteJid' => '5511999999999@s.whatsapp.net',
                'fromMe' => true,
            ],
            'status' => 'PENDING',
        ], 201),
    ]);

    $result = $provider->sendText($instance, new SendTextData(
        phone: '5511999999999',
        message: 'Ola, Evolution',
        mentioned: ['5511988887777'],
        delayMessage: 5,
    ));

    expect($result->success)->toBeTrue();
    expect($result->providerMessageId)->toBe('EVOLUTION-MSG-1');

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->method() === 'POST'
            && str_contains($request->url(), '/message/sendText/')
            && $payload['number'] === '5511999999999'
            && $payload['textMessage']['text'] === 'Ola, Evolution'
            && $payload['options']['delay'] === 5000
            && $payload['options']['mentions']['mentioned'][0] === '5511988887777@s.whatsapp.net';
    });
});

it('paginates chats through the evolution adapter', function () {
    $instance = WhatsAppInstance::factory()->evolution()->create();
    $provider = app(EvolutionWhatsAppProvider::class);

    Http::fake([
        'https://evolution.example.com/chat/findChats/*' => Http::response([
            ['id' => 'chat-1', 'conversationTimestamp' => 1],
            ['id' => 'chat-2', 'conversationTimestamp' => 2],
            ['id' => 'chat-3', 'conversationTimestamp' => 3],
        ], 200),
    ]);

    $result = $provider->getChats($instance, page: 2, pageSize: 1);

    expect($result->success)->toBeTrue();
    expect($result->chats)->toHaveCount(1);
    expect($result->chats[0]['id'])->toBe('chat-2');
});

it('creates groups through the evolution adapter and resolves the remote group id', function () {
    $instance = WhatsAppInstance::factory()->evolution()->create();
    $provider = app(EvolutionWhatsAppProvider::class);

    Http::fake([
        'https://evolution.example.com/group/create/*' => Http::response([], 200),
        'https://evolution.example.com/group/fetchAllGroups/*' => Http::response([
            [
                'id' => '120363295648424210@g.us',
                'subject' => 'Time Comercial',
                'creation' => 1714769954,
            ],
        ], 200),
    ]);

    $result = $provider->createGroup($instance, new CreateGroupData(
        groupName: 'Time Comercial',
        phones: ['5511999999999', '5511988887777'],
        autoInvite: true,
    ));

    expect($result->success)->toBeTrue();
    expect($result->groupId)->toBe('120363295648424210@g.us');
});

it('maps group settings updates to evolution actions', function () {
    $instance = WhatsAppInstance::factory()->evolution()->create();
    $provider = app(EvolutionWhatsAppProvider::class);

    Http::fake([
        'https://evolution.example.com/group/updateSetting/*' => Http::response([], 200),
    ]);

    $result = $provider->updateGroupSettings($instance, new UpdateGroupSettingsData(
        groupId: '120363295648424210@g.us',
        adminOnlyMessage: true,
        adminOnlySettings: false,
        requireAdminApproval: false,
        adminOnlyAddMember: false,
    ));

    expect($result->success)->toBeTrue();

    $actions = [];

    Http::assertSent(function ($request) use (&$actions) {
        if (str_contains($request->url(), '/group/updateSetting/')) {
            $actions[] = $request->data()['action'] ?? null;
        }

        return true;
    });

    expect($actions)->toBe(['announcement', 'unlocked']);
});

it('fetches the remote group catalog through the evolution adapter', function () {
    $instance = WhatsAppInstance::factory()->evolution()->create();
    $provider = app(EvolutionWhatsAppProvider::class);

    Http::fake([
        'https://evolution.example.com/group/fetchAllGroups/*' => Http::response([
            [
                'id' => '120363295648424210@g.us',
                'subject' => 'Time Comercial',
                'participants' => [
                    ['id' => '5511999999999@s.whatsapp.net'],
                ],
            ],
        ], 200),
    ]);

    $result = $provider->fetchGroups($instance, true);

    expect($result->success)->toBeTrue();
    expect($result->includesParticipants)->toBeTrue();
    expect($result->groups[0]['subject'])->toBe('Time Comercial');
});

it('finds remote messages through the evolution adapter', function () {
    $instance = WhatsAppInstance::factory()->evolution()->create();
    $provider = app(EvolutionWhatsAppProvider::class);

    Http::fake([
        'https://evolution.example.com/chat/findMessages/*' => Http::response([
            [
                'key' => [
                    'id' => 'MSG-1',
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => false,
                ],
                'message' => [
                    'conversation' => 'Mensagem recebida',
                ],
            ],
        ], 200),
    ]);

    $result = $provider->findMessages($instance, '5511999999999', [
        'limit' => 30,
        'from_me' => false,
    ]);

    expect($result->success)->toBeTrue();
    expect($result->remoteJid)->toBe('5511999999999@s.whatsapp.net');
    expect($result->messages[0]['key']['id'])->toBe('MSG-1');

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->method() === 'POST'
            && str_contains($request->url(), '/chat/findMessages/')
            && data_get($payload, 'where.key.remoteJid') === '5511999999999@s.whatsapp.net'
            && data_get($payload, 'limit') === 30;
    });
});
