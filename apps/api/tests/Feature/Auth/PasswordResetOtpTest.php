<?php

use App\Modules\Auth\Notifications\PasswordResetOtpNotification;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Users\Models\User;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppProvider;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function createPasswordResetAuthWhatsAppInstance(): WhatsAppInstance
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
        'name' => 'Password Reset OTP',
        'external_instance_id' => 'auth-' . Str::random(8),
        'provider_token' => 'token',
        'provider_client_token' => 'client-token',
        'phone_number' => '5551999999999',
        'status' => 'connected',
        'connected_at' => now(),
        'created_by' => $creator->id,
    ]);
}

it('starts password reset flow via whatsapp with otp session metadata', function () {
    Queue::fake();

    $instance = createPasswordResetAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    User::factory()->create([
        'phone' => '5551998887766',
    ]);

    $response = $this->apiPost('/auth/forgot-password', [
        'login' => '(51) 99888-7766',
    ]);

    $this->assertApiSuccess($response);

    $response->assertJsonPath('data.method', 'whatsapp');
    $response->assertJsonPath('data.resend_in', 30);
    $response->assertJsonPath('data.destination_masked', '+55 (51) *****7766');

    expect($response->json('data.session_token'))->toBeString()->not->toBe('');
    expect($response->json('data.debug_code'))->toHaveLength(6);

    $this->assertDatabaseHas('whatsapp_messages', [
        'instance_id' => $instance->id,
        'recipient_phone' => '5551998887766',
    ]);

    Queue::assertPushed(SendWhatsAppMessageJob::class);
});

it('blocks forgot-password resend before cooldown finishes', function () {
    Queue::fake();

    $instance = createPasswordResetAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    User::factory()->create([
        'phone' => '5541998877665',
    ]);

    $requestResponse = $this->apiPost('/auth/forgot-password', [
        'login' => '(41) 99887-7665',
    ]);

    $response = $this->apiPost('/auth/forgot-password/resend-otp', [
        'session_token' => $requestResponse->json('data.session_token'),
    ]);

    $response->assertStatus(429);
});

it('requires otp verification before resetting the password', function () {
    Queue::fake();

    $instance = createPasswordResetAuthWhatsAppInstance();
    config(['whatsapp.auth.instance_id' => $instance->id]);

    $user = User::factory()->create([
        'phone' => '5511987654321',
    ]);

    $requestResponse = $this->apiPost('/auth/forgot-password', [
        'login' => '(11) 98765-4321',
    ]);

    $sessionToken = $requestResponse->json('data.session_token');
    $code = $requestResponse->json('data.debug_code');

    $verifyResponse = $this->apiPost('/auth/forgot-password/verify-otp', [
        'session_token' => $sessionToken,
        'code' => $code,
    ]);

    $this->assertApiSuccess($verifyResponse);
    $verifyResponse->assertJsonPath('data.message', 'Codigo validado com sucesso.');

    $resetResponse = $this->apiPost('/auth/reset-password', [
        'session_token' => $sessionToken,
        'password' => 'NovaSenha@123',
        'password_confirmation' => 'NovaSenha@123',
        'device_name' => 'web-panel',
    ]);

    $this->assertApiSuccess($resetResponse);

    expect(Hash::check('NovaSenha@123', $user->fresh()->password))->toBeTrue();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});

it('sends password reset otp by email when login is an email address', function () {
    Notification::fake();

    User::factory()->create([
        'email' => 'marina@example.com',
    ]);

    $response = $this->apiPost('/auth/forgot-password', [
        'login' => 'marina@example.com',
    ]);

    $this->assertApiSuccess($response);
    $response->assertJsonPath('data.method', 'email');

    Notification::assertSentOnDemand(PasswordResetOtpNotification::class, function (
        PasswordResetOtpNotification $notification,
        array $channels,
        object $notifiable
    ) {
        return ($notifiable->routes['mail'] ?? null) === 'marina@example.com'
            && in_array('mail', $channels, true);
    });
});

it('uses z-api send-text when auth env sender is configured', function () {
    Http::fake([
        'https://api.z-api.io/*' => Http::response([
            'id' => 'msg-1',
            'zaapId' => 'zaap-1',
        ], 200),
    ]);

    config([
        'whatsapp.auth.instance_id' => null,
        'whatsapp.auth.allow_env_sender_in_testing' => true,
        'whatsapp.auth.zapi.instance_id' => '3BDB98A79042D03232CC1ABE514C6FD4',
        'whatsapp.auth.zapi.token' => 'AC297A57303A1658CCE58316',
        'whatsapp.auth.zapi.client_token' => 'Fac264466087a4bffab5f23bc557d9d15S',
    ]);

    User::factory()->create([
        'phone' => '5511999998888',
    ]);

    $response = $this->apiPost('/auth/forgot-password', [
        'login' => '(11) 99999-8888',
    ]);

    $this->assertApiSuccess($response);

    $debugCode = $response->json('data.debug_code');

    Http::assertSent(function (HttpRequest $request) use ($debugCode) {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.z-api.io/instances/3BDB98A79042D03232CC1ABE514C6FD4/token/AC297A57303A1658CCE58316/send-text'
            && $request->hasHeader('Client-Token', 'Fac264466087a4bffab5f23bc557d9d15S')
            && ($request['phone'] ?? null) === '5511999998888'
            && str_contains((string) ($request['message'] ?? ''), (string) $debugCode);
    });
});
