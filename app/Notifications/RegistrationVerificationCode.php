<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Código de verificación — Hacienda Monterrico')->greeting('Verifica tu correo')->line('Usa este código para activar tu cuponera:')->line($this->code)->line('El código vence en 5 minutos. Si no solicitaste el registro, ignora este mensaje.');
    }
}
