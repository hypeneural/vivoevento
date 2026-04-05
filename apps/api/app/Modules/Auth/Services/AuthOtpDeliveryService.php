<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Notifications\PasswordResetOtpNotification;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use App\Modules\WhatsApp\Services\WhatsAppProviderResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthOtpDeliveryService
{
    public function __construct(
        private readonly WhatsAppMessagingService $messagingService,
        private readonly WhatsAppProviderResolver $providerResolver,
    ) {}

    public function sendSignupWhatsAppOtp(string $phone, string $code): void
    {
        $this->sendWhatsAppMessage(
            $phone,
            implode("\n", [
                '✨ Evento Vivo',
                '',
                "Seu código para criar a sua conta é: *{$code}*",
                'Ele expira em 15 minutos.',
                'Se não foi você, ignore esta mensagem.',
            ]),
            'signup'
        );
    }

    public function sendPasswordResetWhatsAppOtp(string $phone, string $code): void
    {
        $this->sendWhatsAppMessage(
            $phone,
            implode("\n", [
                '🔐 Evento Vivo',
                '',
                "Seu código para redefinir a senha é: *{$code}*",
                'Ele expira em 15 minutos.',
                'Se você não pediu essa alteração, ignore esta mensagem.',
            ]),
            'password_reset'
        );
    }

    public function sendPasswordResetEmailOtp(string $email, string $code): void
    {
        Notification::route('mail', $email)
            ->notifyNow(new PasswordResetOtpNotification($code));
    }

    private function sendWhatsAppMessage(string $phone, string $message, string $context): void
    {
        $envInstance = $this->resolveEnvZApiInstance();

        if ($envInstance !== null) {
            $this->providerResolver
                ->forProviderKey('zapi')
                ->sendText(
                    $envInstance,
                    new SendTextData(
                        phone: $phone,
                        message: $message,
                    )
                );

            return;
        }

        $databaseInstance = $this->resolveDatabaseAuthInstance();

        if ($databaseInstance !== null) {
            $this->messagingService->sendText(
                $databaseInstance,
                new SendTextData(
                    phone: $phone,
                    message: $message,
                )
            );

            return;
        }

        Log::info('Auth OTP generated without WhatsApp dispatch.', [
            'context' => $context,
            'phone' => $phone,
        ]);
    }

    private function resolveEnvZApiInstance(): ?WhatsAppInstance
    {
        if (app()->environment('testing') && ! config('whatsapp.auth.allow_env_sender_in_testing', false)) {
            return null;
        }

        $instanceId = trim((string) config('whatsapp.auth.zapi.instance_id'));
        $token = trim((string) config('whatsapp.auth.zapi.token'));
        $clientToken = trim((string) config('whatsapp.auth.zapi.client_token'));

        if ($instanceId === '' || $token === '' || $clientToken === '') {
            return null;
        }

        return new WhatsAppInstance([
            'provider_key' => 'zapi',
            'name' => 'Auth OTP Sender',
            'external_instance_id' => $instanceId,
            'provider_token' => $token,
            'provider_client_token' => $clientToken,
            'status' => 'connected',
        ]);
    }

    private function resolveDatabaseAuthInstance(): ?WhatsAppInstance
    {
        $configuredInstanceId = config('whatsapp.auth.instance_id');

        if ($configuredInstanceId) {
            $instance = WhatsAppInstance::query()->find($configuredInstanceId);

            if (! $instance) {
                throw new HttpException(503, 'Instancia de autenticacao do WhatsApp nao encontrada.');
            }

            if (! $instance->isConnected()) {
                throw new HttpException(503, 'A instancia de autenticacao do WhatsApp esta desconectada.');
            }

            return $instance;
        }

        if (app()->environment(['local', 'testing'])) {
            return null;
        }

        $instances = WhatsAppInstance::query()
            ->connected()
            ->limit(2)
            ->get();

        if ($instances->count() === 1) {
            return $instances->first();
        }

        throw new HttpException(503, 'Instancia de autenticacao do WhatsApp nao configurada.');
    }
}
