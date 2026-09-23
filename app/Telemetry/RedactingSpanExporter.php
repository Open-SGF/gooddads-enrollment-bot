<?php

declare(strict_types=1);

namespace App\Telemetry;

use Closure;
use Generator;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\LinkInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\StatusData;
use OpenTelemetry\SDK\Trace\StatusDataInterface;

final readonly class RedactingSpanExporter implements SpanExporterInterface
{
    public function __construct(private SpanExporterInterface $exporter) {}

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $apiKey = config('services.neon.api_key');
        $baseUrl = config('services.neon.base_url');
        $host = is_string($baseUrl) ? parse_url($baseUrl, PHP_URL_HOST) : null;
        $redact = static function (string $value) use ($apiKey): string {
            if (! is_string($apiKey) || $apiKey === '') {
                return $value;
            }

            return str_replace(array_unique([$apiKey, rawurlencode($apiKey), urlencode($apiKey)]), '[REDACTED]', $value);
        };
        $attributes = static function (AttributesInterface $original) use ($host, $redact): AttributesInterface {
            /** @var array<string, mixed> $values */
            $values = $original->toArray();
            foreach ($values as $name => &$value) {
                if ($name === 'url.full' && is_string($value) && is_string($host)) {
                    $urlHost = parse_url($value, PHP_URL_HOST);
                    if (is_string($urlHost) && strcasecmp($urlHost, $host) === 0) {
                        $value = preg_replace('/([?&]key=)[^&#]*/i', '$1[REDACTED]', $value);
                    }
                }

                if (is_string($value)) {
                    $value = $redact($value);
                } elseif (is_array($value)) {
                    $value = array_map(static fn ($item): mixed => is_string($item) ? $redact($item) : $item, $value);
                }
            }

            unset($value);

            return new RedactedAttributes($original, $values);
        };

        $redacted = (static function () use ($batch, $attributes, $redact): Generator {
            foreach ($batch as $span) {
                yield new RedactedSpanData($span, $attributes, $redact);
            }
        })();

        return $this->exporter->export($redacted, $cancellation);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->exporter->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->exporter->forceFlush($cancellation);
    }
}

/** @internal */
final readonly class RedactedSpanData implements SpanDataInterface
{
    /**
     * @param  Closure(AttributesInterface): AttributesInterface  $attributes
     * @param  Closure(string): string  $redact
     */
    // @phpstan-ignore missingType.iterableValue, missingType.iterableValue (SDK interface extends Traversable without a value type)
    public function __construct(private SpanDataInterface $span, private Closure $attributes, private Closure $redact) {}

    public function getName(): string
    {
        return ($this->redact)($this->span->getName());
    }

    public function getKind(): int
    {
        return $this->span->getKind();
    }

    public function getContext(): SpanContextInterface
    {
        return $this->span->getContext();
    }

    public function getParentContext(): SpanContextInterface
    {
        return $this->span->getParentContext();
    }

    public function getTraceId(): string
    {
        return $this->span->getTraceId();
    }

    public function getSpanId(): string
    {
        return $this->span->getSpanId();
    }

    public function getParentSpanId(): string
    {
        return $this->span->getParentSpanId();
    }

    public function getStartEpochNanos(): int
    {
        return $this->span->getStartEpochNanos();
    }

    public function getEndEpochNanos(): int
    {
        return $this->span->getEndEpochNanos();
    }

    public function getInstrumentationScope(): InstrumentationScopeInterface
    {
        return $this->span->getInstrumentationScope();
    }

    public function getResource(): ResourceInfo
    {
        return $this->span->getResource();
    }

    public function getTotalDroppedEvents(): int
    {
        return $this->span->getTotalDroppedEvents();
    }

    public function getTotalDroppedLinks(): int
    {
        return $this->span->getTotalDroppedLinks();
    }

    public function hasEnded(): bool
    {
        return $this->span->hasEnded();
    }

    // @phpstan-ignore missingType.iterableValue (SDK interface extends Traversable without a value type)
    public function getAttributes(): AttributesInterface
    {
        return ($this->attributes)($this->span->getAttributes());
    }

    /** @return list<LinkInterface> */
    public function getLinks(): array
    {
        return $this->span->getLinks();
    }

    /** @return list<EventInterface> */
    public function getEvents(): array
    {
        return array_map(fn (EventInterface $event): EventInterface => new RedactedEvent(
            $event,
            ($this->redact)($event->getName()),
            ($this->attributes)($event->getAttributes()),
        ), $this->span->getEvents());
    }

    public function getStatus(): StatusDataInterface
    {
        $status = $this->span->getStatus();

        return match ($status->getCode()) {
            StatusCode::STATUS_OK => StatusData::ok(),
            StatusCode::STATUS_ERROR => StatusData::create(StatusCode::STATUS_ERROR, ($this->redact)($status->getDescription())),
            default => StatusData::unset(),
        };
    }
}
