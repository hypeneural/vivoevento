<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('🔐 Seu código de recuperação - Evento Vivo')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para redefinir a sua senha no Evento Vivo.')
            ->line("Seu código de validação é: {$this->code}")
            ->line('Esse código expira em 15 minutos.')
            ->line('Se não foi você, pode ignorar esta mensagem com segurança.');
    }
}
