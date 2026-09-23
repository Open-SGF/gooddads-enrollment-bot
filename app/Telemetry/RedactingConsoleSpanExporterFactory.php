<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

final class RedactingConsoleSpanExporterFactory implements SpanExporterFactoryInterface
{
    public function create(): SpanExporterInterface
    {
        return new RedactingSpanExporter((new ConsoleSpanExporterFactory())->create());
    }
}
