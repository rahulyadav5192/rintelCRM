<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureUserIsActive::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('lookup/*') || $request->expectsJson(),
        );

        // Inertia visits get a friendly redirect-back with a toast instead of a raw 403 page.
        $exceptions->render(function (AuthorizationException|HttpException $e, Request $request) {
            $status = $e instanceof HttpException ? $e->getStatusCode() : 403;
            if ($status === 403 && $request->header('X-Inertia') && ! $request->isMethod('get')) {
                return back()->with('error', $e->getMessage() ?: 'You are not authorized to perform this action.');
            }

            return null;
        });

        // Branded error pages for full-page GET visits (JSON callers still get JSON).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            $debugServerError = $status >= 500 && config('app.debug');
            if (in_array($status, [403, 404, 419, 500, 503], true) && ! $debugServerError && ! $request->expectsJson() && $request->isMethod('get')) {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
