<?php

declare(strict_types=1);

use App\Http\Middleware\DropboxBasicAuth;
use App\Telemetry\RedactingConsoleSpanExporterFactory;
use App\Telemetry\RedactingSpanExporterFactory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use OpenTelemetry\SDK\Registry;
use Sentry\Laravel\Integration;

// Headless commands install the filter before the SDK creates its exporter.
Registry::registerSpanExporterFactory('otlp', RedactingSpanExporterFactory::class, true);
Registry::registerSpanExporterFactory('console', RedactingConsoleSpanExporterFactory::class, true);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'dropbox.basic' => DropboxBasicAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
