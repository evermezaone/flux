<?php

namespace App\Services\Fcm;

use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * Envio de push FCM (HTTP v1) via Firebase Admin (kreait) — FLX REQ-0028.
 * Manda un "data message" de alta prioridad para despertar al equipo (ping/restart).
 *
 * Mockeable en tests: se resuelve por el contenedor (app(FcmSender::class)); los tests inyectan un
 * doble para no pegarle a Firebase.
 */
class FcmSender
{
    private ?Messaging $messaging = null;

    /** Construye Messaging desde la service account (sin red hasta el send). */
    protected function messaging(): Messaging
    {
        if ($this->messaging === null) {
            $this->messaging = (new Factory)
                ->withServiceAccount(config('firebase.credentials'))
                ->createMessaging();
        }

        return $this->messaging;
    }

    /**
     * Envia un data message al token. Devuelve true si se envio; false si el token ya no es valido
     * (NotFound) -> el caller debe limpiarlo.
     *
     * @param array<string, string> $data
     */
    public function send(string $token, array $data): bool
    {
        $message = CloudMessage::new()
            ->withToken($token)
            ->withData($data)
            ->withAndroidConfig(AndroidConfig::fromArray(['priority' => 'high']));

        try {
            $this->messaging()->send($message);

            return true;
        } catch (NotFound $e) {
            // token desconocido/desregistrado: hay que limpiarlo.
            return false;
        }
    }
}
