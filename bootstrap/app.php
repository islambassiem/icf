<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Language: runs after the session starts, on normal requests and Livewire updates.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Payment providers cannot send a CSRF token. The webhook is protected by its signature instead.
        // (If your Laravel version names this method differently, exclude 'webhooks/*' from CSRF checks.)
        // $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->preventRequestForgery(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
