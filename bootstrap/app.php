<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);

        // Laravel defaults guest redirects to `route('login')`, but this is an
        // API-only application with no such route. Returning null lets the
        // authentication exception bubble up as a 401 JSON response instead
        // of blowing up with "Route [login] not defined." (500).
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is an API-only application: there is no `login` route for the
        // authentication exception to redirect to, so always render JSON for
        // API requests (otherwise unauthenticated calls would 500 instead of
        // returning a clean 401).
        $exceptions->shouldRenderJsonWhen(
            fn ($request, $throwable) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
