<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'device.key' => \App\Http\Middleware\EnsureDeviceKey::class,
        ]);
        // Invitados (navegador) al login del panel; las peticiones JSON reciben 401.
        $middleware->redirectGuestsTo(fn () => '/admin/login');

        // Cabeceras de seguridad en todas las respuestas (REQ-0009).
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuestas de error uniformes para la API: { ok:false, error }.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // panel web: comportamiento por defecto
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Validacion fallida',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['ok' => false, 'error' => 'No autenticado'], 401);
            }
            if ($e instanceof AuthorizationException) {
                return response()->json(['ok' => false, 'error' => 'No autorizado'], 403);
            }
            if ($e instanceof ModelNotFoundException) {
                return response()->json(['ok' => false, 'error' => 'Recurso no encontrado'], 404);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = $e->getMessage();
            if ($status >= 500 && ! config('app.debug')) {
                $message = 'Error interno';
            }

            return response()->json([
                'ok' => false,
                'error' => $message !== '' ? $message : 'Error',
            ], $status);
        });
    })->create();
