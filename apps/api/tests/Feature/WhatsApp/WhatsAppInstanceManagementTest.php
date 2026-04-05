<?php

use App\Modules\WhatsApp\Clients\Contracts\WhatsAppProviderInterface;
use App\Modules\WhatsApp\Clients\DTOs\ProviderConnectionDetailsData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderHealthCheckData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderQrCodeData;
use App\Modules\WhatsApp\Clients\DTOs\ProviderStatusData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;

afterEach(function () {
    Mockery::close();
});

function bindProviderResolver(WhatsAppProviderInterface $provider): void
{
    $resolver = Mockery::mock(WhatsAppProviderResolver::class);
    $resolver->shouldReceive('forInstance')->andReturn($provider);

    app()->instance(WhatsAppProviderResolver::class, $resolver);
}

it('creates a z-api instance and masks provider secrets in the response', function () {
    [$user, $organization] = $this->actingAsOwner();

    $response = $this->apiPost('/whatsapp/instances', [
        'provider_key' => 'zapi',
        'name' => 'Comercial Principal',
        'instance_name' => 'comercial-01',
        'phone_number' => '+55 (11) 99999-9999',
        'is_active' => true,
        'provider_config' => [
            'instance_id' => 'ZAPI-COM-01',
            'instance_token' => 'token-1234',
            'client_token' => 'client-9876',
            'base_url' => 'https://api.z-api.io',
        ],
        'settings' => [
            'timeout_seconds' => 30,
            'tags' => ['comercial', 'principal'],
        ],
    ]);

    $this->assertApiSuccess($response, 201);

    expect($response->json('data.provider_key'))->toBe('zapi');
    expect($response->json('data.provider.label'))->toBe('Z-API');
    expect($response->json('data.instance_name'))->toBe('comercial_01');
    expect($response->json('data.provider_config.instance_id'))->toBe('ZAPI-COM-01');
    expect($response->json('data.provider_config.instance_token_configured'))->toBeTrue();
    expect($response->json('data.provider_config.instance_token_masked'))->toBe('****1234');
    expect($response->json('data.provider_config.client_token_masked'))->toBe('****9876');

    $this->assertDatabaseHas('whatsapp_instances', [
        'organization_id' => $organization->id,
        'name' => 'Comercial Principal',
        'instance_name' => 'comercial_01',
        'provider_key' => 'zapi',
        'external_instance_id' => 'ZAPI-COM-01',
        'phone_number' => '5511999999999',
    ]);
});

it('filters whatsapp instances by provider within the current organization', function () {
    [$user, $organization] = $this->actingAsOwner();

    WhatsAppInstance::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'ZAPI Operacao',
    ]);

    $evolution = WhatsAppInstance::factory()->evolution()->create([
        'organization_id' => $organization->id,
        'name' => 'Evolution Atendimento',
        'status' => 'connected',
    ]);

    $otherOrganization = $this->createOrganization();
    WhatsAppInstance::factory()->evolution()->create([
        'organization_id' => $otherOrganization->id,
        'name' => 'Evolution Outro Tenant',
    ]);

    $response = $this->apiGet('/whatsapp/instances?provider_key=evolution&search=Atendimento');

    $this->assertApiSuccess($response);
    $this->assertApiPaginated($response);

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($evolution->id);
    expect($response->json('data.0.provider_key'))->toBe('evolution');
    expect($response->json('data.0.provider.label'))->toBe('Evolution API');
});

it('tests a connection and persists the unified health state', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'configured',
        'last_health_check_at' => null,
        'last_health_status' => null,
    ]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('testConnection')
        ->once()
        ->withArgs(fn (WhatsAppInstance $resolved) => $resolved->is($instance))
        ->andReturn(new ProviderHealthCheckData(
            success: true,
            connected: false,
            status: 'disconnected',
            message: 'Sessao autenticada, aguardando leitura do QR.',
            phone: '5511988887777',
            meta: ['provider_status' => 'qr_pending'],
        ));

    bindProviderResolver($provider);

    $response = $this->apiPost("/whatsapp/instances/{$instance->id}/test-connection");

    $this->assertApiSuccess($response);
    expect($response->json('data.connected'))->toBeFalse();
    expect($response->json('data.status'))->toBe('disconnected');
    expect($response->json('data.instance.last_health_status'))->toBe('disconnected');

    $instance->refresh();

    expect($instance->last_health_status)->toBe('disconnected');
    expect($instance->phone_number)->toBe('5511988887777');
    expect($instance->provider_meta_json['provider_status'] ?? null)->toBe('qr_pending');
});

it('switches the default instance after a successful health check', function () {
    [$user, $organization] = $this->actingAsOwner();

    $currentDefault = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'is_default' => true,
        'last_health_status' => 'connected',
    ]);

    $candidate = WhatsAppInstance::factory()->evolution()->create([
        'organization_id' => $organization->id,
        'is_default' => false,
        'is_active' => true,
        'status' => 'disconnected',
        'last_health_check_at' => now(),
        'last_health_status' => 'disconnected',
    ]);

    $response = $this->apiPost("/whatsapp/instances/{$candidate->id}/set-default");

    $this->assertApiSuccess($response);
    expect($response->json('data.id'))->toBe($candidate->id);
    expect($response->json('data.is_default'))->toBeTrue();

    $currentDefault->refresh();
    $candidate->refresh();

    expect($currentDefault->is_default)->toBeFalse();
    expect($candidate->is_default)->toBeTrue();
});

it('returns a unified qr connection state for disconnected instances', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'configured',
        'phone_number' => '5511999999999',
    ]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('getStatus')
        ->once()
        ->withArgs(fn (WhatsAppInstance $resolved) => $resolved->is($instance))
        ->andReturn(new ProviderStatusData(
            connected: false,
            smartphoneConnected: false,
            error: 'You are not connected.',
        ));
    $provider->shouldReceive('getQrCodeImage')
        ->once()
        ->withArgs(fn (WhatsAppInstance $resolved) => $resolved->is($instance))
        ->andReturn(new ProviderQrCodeData(
            qrCodeBase64Image: 'base64-qr-image',
        ));

    bindProviderResolver($provider);

    $response = $this->apiGet("/whatsapp/instances/{$instance->id}/connection-state");

    $this->assertApiSuccess($response);
    expect($response->json('data.connected'))->toBeFalse();
    expect($response->json('data.status_message'))->toBe('You are not connected.');
    expect($response->json('data.qr_available'))->toBeTrue();
    expect($response->json('data.qr_render_mode'))->toBe('image');
    expect($response->json('data.qr_code'))->toBe('data:image/png;base64,base64-qr-image');
    expect($response->json('data.qr_expires_in_sec'))->toBe(20);
});

it('returns profile and device details when the instance is connected', function () {
    [$user, $organization] = $this->actingAsOwner();

    $instance = WhatsAppInstance::factory()->connected()->create([
        'organization_id' => $organization->id,
        'phone_number' => '5511999999999',
    ]);

    $provider = Mockery::mock(WhatsAppProviderInterface::class);
    $provider->shouldReceive('getStatus')
        ->once()
        ->withArgs(fn (WhatsAppInstance $resolved) => $resolved->is($instance))
        ->andReturn(new ProviderStatusData(
            connected: true,
            smartphoneConnected: true,
        ));
    $provider->shouldReceive('getConnectionDetails')
        ->once()
        ->withArgs(fn (WhatsAppInstance $resolved) => $resolved->is($instance))
        ->andReturn(new ProviderConnectionDetailsData(
            phone: '5511999999999',
            profile: [
                'lid' => '5511999999999@lid',
                'name' => 'Equipe Comercial',
                'about' => 'Conta principal',
                'img_url' => 'https://cdn.example.com/profile.png',
                'is_business' => true,
            ],
            device: [
                'session_name' => 'API_MASTER_1',
                'device_model' => 'iPhone 15',
                'original_device' => 'iphone',
            ],
        ));

    bindProviderResolver($provider);

    $response = $this->apiGet("/whatsapp/instances/{$instance->id}/connection-state");

    $this->assertApiSuccess($response);
    expect($response->json('data.connected'))->toBeTrue();
    expect($response->json('data.smartphone_connected'))->toBeTrue();
    expect($response->json('data.qr_available'))->toBeFalse();
    expect($response->json('data.profile.name'))->toBe('Equipe Comercial');
    expect($response->json('data.device.device_model'))->toBe('iPhone 15');
});
