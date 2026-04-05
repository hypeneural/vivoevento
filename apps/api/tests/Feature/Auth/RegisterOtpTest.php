<?php

use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

function createAuthWhatsAppInstance(): WhatsAppInstance
{
    $organization = Organization::factory()->create();
    $creator = User::factory()->create();
    $provider = WhatsAppProvider::query()->create([
        'key' => 'zapi',
        'name' => 'Z-API',
        'is_active' => true,
    ]);

    return WhatsAppInstance::query()->create([
        'uuid' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'provider_id' => $provider->id,
        'provider_key' => 'zapi',
        'name' => 'Auth OTP',
        'external_instance_id' => 'auth-' . Str::random(8),
        'provider_token' => 'token',
        'provider_client_token' => 'client-token',
        'phone_number' => '5551999999999',
        'status' => 'connected',
        'connected_at' => now(),
        'created_by' => $creator->id,
    ]);
}

it('starts signup flow with whatsapp otp for a new phone', function () {
    Queue::fake();

    $instance = createAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    $response = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Carlos Silva',
        'phone' => '(51) 99888-7766',
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.delivery', 'whatsapp');
    $response->assertJsonPath('data.resend_in', 30);
    $response->assertJsonPath('data.phone_masked', '+55 (51) *****7766');

    expect($response->json('data.session_token'))->toBeString()->not->toBe('');
    expect($response->json('data.debug_code'))->toHaveLength(6);

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $instance->id,
        'recipient_phone' => '5551998887766',
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('rejects signup when whatsapp already exists', function () {
    User::factory()->create([
        'phone' => '5551998887766',
    ]);

    $response = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Carlos Silva',
        'phone' => '(51) 99888-7766',
    ]);

    $this->assertApiValidationError($response, ['phone']);
});

it('creates the user and authenticates after a valid otp', function () {
    $this->seedPermissions();
    Queue::fake();

    $instance = createAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    $requestResponse = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Marina Costa',
        'phone' => '(11) 98765-4321',
    ]);

    $sessionToken = $requestResponse->json('data.session_token');
    $code = $requestResponse->json('data.debug_code');

    $verifyResponse = $this->apiPost('/auth/register/verify-otp', [
        'session_token' => $sessionToken,
        'code' => $code,
        'device_name' => 'web-panel',
    ]);

    $this->assertApiSuccess($verifyResponse);
    $verifyResponse->assertJsonPath('data.onboarding.next_path', '/plans');

    $user = User::query()->where('phone', '5511987654321')->first();

    expect($user)->not->toBeNull();
    expect($user?->name)->toBe('Marina Costa');
    expect($user?->hasRole('partner-owner'))->toBeTrue();

    $this->assertDatabaseHas('organization_members', [
        'user_id' => $user->id,
        'role_key' => 'partner-owner',
        'is_owner' => true,
        'status' => 'active',
    ]);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);

    $activity = Activity::query()
        ->where('description', 'Cadastro concluido com OTP via WhatsApp')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity?->event)->toBe('auth.register');
    expect($activity?->subject_type)->toBe(User::class);
    expect($activity?->subject_id)->toBe($user->id);
    expect($activity?->causer_id)->toBe($user->id);
});

it('creates a direct customer organization when signup starts from a single event checkout journey', function () {
    $this->seedPermissions();
    Queue::fake();

    $instance = createAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    $requestResponse = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Ana Carolina',
        'phone' => '(48) 99977-6655',
        'journey' => 'single_event_checkout',
    ]);

    $sessionToken = $requestResponse->json('data.session_token');
    $code = $requestResponse->json('data.debug_code');

    $verifyResponse = $this->apiPost('/auth/register/verify-otp', [
        'session_token' => $sessionToken,
        'code' => $code,
        'device_name' => 'web-panel',
    ]);

    $this->assertApiSuccess($verifyResponse);
    $verifyResponse->assertJsonPath('data.onboarding.next_path', '/events/create');

    $user = User::query()->where('phone', '5548999776655')->first();

    expect($user)->not->toBeNull();

    $membership = $user?->organizationMembers()->latest('id')->first();

    expect($membership)->not->toBeNull();
    expect($membership?->organization?->type?->value)->toBe('direct_customer');
});

it('blocks resend before the 30 second cooldown finishes', function () {
    Queue::fake();

    $instance = createAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    $requestResponse = $this->apiPost('/auth/register/request-otp', [
        'name' => 'Julia Lopes',
        'phone' => '(41) 99988-7766',
    ]);

    $response = $this->apiPost('/auth/register/resend-otp', [
        'session_token' => $requestResponse->json('data.session_token'),
    ]);

    $response->assertStatus(429);
});
