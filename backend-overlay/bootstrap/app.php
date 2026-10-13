<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API murni JSON: jangan mengalihkan tamu ke route 'login' (tidak ada) -> tetap 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error di /api/* memakai format { code, message, errors } (NFR-09).
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());

        $json = fn (string $code, string $message, int $status, $errors = null) =>
            response()->json(['code' => $code, 'message' => $message, 'errors' => $errors], $status);

        $exceptions->render(function (ValidationException $e, Request $r) use ($json) {
            if ($r->is('api/*')) {
                return $json('VALIDATION_ERROR', 'Data yang dikirim tidak valid.', 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) use ($json) {
            if ($r->is('api/*')) {
                return $json('UNAUTHENTICATED', 'Token tidak ada, tidak valid, atau sudah dicabut.', 401);
            }
        });
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $r) use ($json) {
            if ($r->is('api/*')) {
                return $json('NOT_FOUND', 'Data tidak ditemukan.', 404);
            }
        });
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $r) use ($json) {
            if ($r->is('api/*')) {
                return $json('TOO_MANY_REQUESTS', 'Terlalu banyak percobaan. Coba lagi sebentar lagi.', 429);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $r) use ($json) {
            if ($r->is('api/*') && $e->getStatusCode() === 403) {
                return $json('FORBIDDEN', $e->getMessage() ?: 'Anda tidak memiliki akses ke sumber daya ini.', 403);
            }
        });
    })->create();
