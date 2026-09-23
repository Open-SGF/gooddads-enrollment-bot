<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\Contrib\Otlp\SpanExporterFactory as OtlpSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanExporter\SpanExporterFactoryInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

final class RedactingSpanExporterFactory implements SpanExporterFactoryInterface
{
    public function create(): SpanExporterInterface
    {
        return new RedactingSpanExporter(new OtlpSpanExporterFactory()->create());
    }
}
