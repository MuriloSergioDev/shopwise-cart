<?php

declare(strict_types=1);

use App\Http\Middleware\OptionalJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')->prefix('internal')->name('internal.')->group(base_path('routes/internal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.jwt.optional' => OptionalJwt::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Services only speak JSON; browsers never reach them directly (the Nuxt BFF does).
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
    })->create();
