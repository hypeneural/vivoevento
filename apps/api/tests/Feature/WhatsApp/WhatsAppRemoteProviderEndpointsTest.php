<?php

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppProviderInterface;
use App\Modules\WhatsApp\Clients\DTOs\ProviderChatMessagesData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderGroupCatalogData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderGroupParticipantsData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;

afterEach(function () {
    Mockery::close();
});

it('lists the remote provider group catalog', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->evolution()->create([
        'organization_id' => $organization->id,
    ]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('fetchGroups')
        ->once()
        ->andReturn(new ProviderGroupCatalogData(
            success: true,
            groups: [[
                'id' => '120363295648424210@g.us',
                'subject' => 'Time Comercial',
                'participants' => [
                    ['id' => '5511999999999@s.whatsapp.net', 'admin' => 'admin'],
                ],
            ]],
            includesParticipants: true,
        ));

    $resolver = Mockery::mock(WhatsAppProviderResolver::class);
    $resolver->shouldReceive('forInstance')->andReturn($provider);
    app()->instance(WhatsAppProviderResolver::class, $resolver);

    $response = $this->apiGet("/whatsapp/group-management/catalog?instance_id={$instance->id}&include_participants=1");

    $this->assertApiSuccess($response);
    expect($response->json('data.includes_participants'))->toBeTrue();
    expect($response->json('data.groups.0.id'))->toBe('120363295648424210@g.us');
    expect($response->json('data.groups.0.participants_count'))->toBe(1);
});

it('lists remote group participants for a provider group', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->evolution()->create([
        'organization_id' => $organization->id,
    ]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('getGroupParticipants')
        ->once()
        ->andReturn(new ProviderGroupParticipantsData(
            success: true,
            groupId: '120363295648424210@g.us',
            participants: [
                ['id' => '5511999999999@s.whatsapp.net', 'admin' => 'admin', 'name' => 'Ana'],
                ['id' => '5511988887777@s.whatsapp.net', 'admin' => null, 'name' => 'Bruno'],
            ],
        ));

    $resolver = Mockery::mock(WhatsAppProviderResolver::class);
    $resolver->shouldReceive('forInstance')->andReturn($provider);
    app()->instance(WhatsAppProviderResolver::class, $resolver);

    $response = $this->apiGet("/whatsapp/group-management/120363295648424210@g.us/participants?instance_id={$instance->id}");

    $this->assertApiSuccess($response);
    expect($response->json('data.group_id'))->toBe('120363295648424210@g.us');
    expect($response->json('data.participants'))->toHaveCount(2);
});

it('finds remote chat messages through the provider', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->evolution()->create([
        'organization_id' => $organization->id,
    ]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('findMessages')
        ->once()
        ->andReturn(new ProviderChatMessagesData(
            success: true,
            remoteJid: '5511999999999@s.whatsapp.net',
            messages: [[
                'key' => [
                    'id' => 'MSG-1',
                    'remoteJid' => '5511999999999@s.whatsapp.net',
                    'fromMe' => true,
                ],
                'messageTimestamp' => 1715000000,
                'message' => [
                    'conversation' => 'Ola',
                ],
            ]],
        ));

    $resolver = Mockery::mock(WhatsAppProviderResolver::class);
    $resolver->shouldReceive('forInstance')->andReturn($provider);
    app()->instance(WhatsAppProviderResolver::class, $resolver);

    $response = $this->apiPost('/whatsapp/chats/find-messages', [
        'instance_id' => $instance->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'limit' => 20,
    ]);

    $this->assertApiSuccess($response);
    expect($response->json('data.remote_jid'))->toBe('5511999999999@s.whatsapp.net');
    expect($response->json('data.messages.0.id'))->toBe('MSG-1');
});
