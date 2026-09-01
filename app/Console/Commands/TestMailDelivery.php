<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailDelivery extends Command
{
    protected $signature = 'mail:test {email? : Dirección destinataria; por defecto usa MAIL_FROM_ADDRESS}';

    protected $description = 'Envía un correo real de diagnóstico usando la configuración activa';

    public function handle(): int
    {
        $recipient = $this->argument('email') ?: config('mail.from.address');

        if (! $recipient) {
            $this->error('No hay destinatario ni MAIL_FROM_ADDRESS configurado.');

            return self::FAILURE;
        }

        Mail::raw(
            'El correo de desarrollo de Hacienda Monterrico funciona correctamente. Fecha: '.now()->toDateTimeString(),
            fn ($message) => $message->to($recipient)->subject('Prueba de correo — Hacienda Monterrico')
        );

        $this->info('Correo aceptado por el servidor SMTP configurado. Revisa la bandeja de desarrollo.');

        return self::SUCCESS;
    }
}
