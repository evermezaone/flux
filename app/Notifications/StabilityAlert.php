<?php

namespace App\Notifications;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FLX-0047: alerta de estabilidad de un equipo. Se envia al entrar/ESCALAR a warn/critical y al recuperarse.
 */
class StabilityAlert extends Notification
{
    use Queueable;

    public function __construct(
        public Device $device,
        public string $status,
        public bool $recovered,
        public string $detail = '',
    ) {
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $code = $this->device->code;
        if ($this->recovered) {
            return (new MailMessage)
                ->subject("VialSense: estabilidad de {$code} recuperada")
                ->line("El equipo {$code} volvió a estabilidad normal.");
        }

        return (new MailMessage)
            ->subject("VialSense: estabilidad de {$code} en {$this->status}")
            ->line("El equipo {$code} reporta inestabilidad ({$this->status}).")
            ->line($this->detail !== '' ? $this->detail : 'Revisá el panel de Salud (columna Estabilidad) para el detalle.');
    }
}
