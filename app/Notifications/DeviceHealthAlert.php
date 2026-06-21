<?php

namespace App\Notifications;

use App\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta de salud de un equipo (FLX REQ-0026): se envia al CAER (fail/offline) y al RECUPERARSE.
 */
class DeviceHealthAlert extends Notification
{
    use Queueable;

    public function __construct(
        public Device $device,
        public string $status,
        public bool $recovered,
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
                ->subject("VialSense: equipo {$code} recuperado")
                ->line("El equipo {$code} volvió a estado normal ({$this->status}).");
        }

        $label = $this->status === 'offline' ? 'sin reportar (offline)' : "con fallas ({$this->status})";

        return (new MailMessage)
            ->subject("VialSense: equipo {$code} {$label}")
            ->line("El equipo {$code} está {$label}.")
            ->line('Revisá el panel de salud para el detalle por subsistema.');
    }
}
