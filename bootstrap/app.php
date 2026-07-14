<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ApplyAmoWidgetFramePolicy;
use App\Http\Middleware\HandleInertiaRequests;
use App\Providers\AppServiceProvider;
use App\Services\Alerts\TelegramNotifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'amo-widget-frame-policy' => ApplyAmoWidgetFramePolicy::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $e): void {
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
                return;
            }

            app(TelegramNotifier::class)->sendThrottled(
                'exception:'.get_class($e).':'.$e->getFile().':'.$e->getLine(),
                "🔴 Необработанная ошибка\n\n".
                get_class($e)."\n".
                mb_substr($e->getMessage(), 0, 500)."\n".
                $e->getFile().':'.$e->getLine()
            );
        });
    })
    ->withProviders([
        AppServiceProvider::class,
    ])
    ->create();
