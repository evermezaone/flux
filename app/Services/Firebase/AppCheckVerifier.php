<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Factory;

/**
 * Verifica un token de Firebase App Check (FLX REQ-0029). Atestigua que la request viene de la app
 * genuina (no manipulada). Mockeable: se resuelve por el contenedor (app(AppCheckVerifier::class)).
 */
class AppCheckVerifier
{
    private mixed $appCheck = null;

    protected function appCheck(): \Kreait\Firebase\Contract\AppCheck
    {
        if ($this->appCheck === null) {
            $this->appCheck = (new Factory)
                ->withServiceAccount(config('firebase.credentials'))
                ->createAppCheck();
        }

        return $this->appCheck;
    }

    /** true si el token de App Check es valido; false si es invalido/ausente. */
    public function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $this->appCheck()->verifyToken($token);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
