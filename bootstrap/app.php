<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'api.token' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API clients such as Postman often send Accept: */*. Always render API
        // validation/authentication failures as JSON instead of redirecting to
        // the web login page and returning misleading HTML with a 200 status.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $exception) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
