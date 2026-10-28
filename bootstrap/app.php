<?php

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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class, 'idempotent' => \App\Http\Middleware\Idempotent::class]);
        $middleware->prependToGroup('api', \App\Http\Middleware\ForceJson::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\EnsureActive::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\CleanInput::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Phone apps often do not send "Accept: application/json". Without this an expired token would be a 500 page and a validation error a redirect.
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
