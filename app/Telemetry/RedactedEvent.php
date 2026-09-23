<?php

declare(strict_types=1);

namespace App\Telemetry;

use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\EventInterface;

final readonly class RedactedEvent implements EventInterface
{
    // @phpstan-ignore missingType.iterableValue (SDK interface extends Traversable without a value type)
    public function __construct(
        private EventInterface $event,
        private string $name,
        private AttributesInterface $attributes,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    // @phpstan-ignore missingType.iterableValue (SDK interface extends Traversable without a value type)
    public function getAttributes(): AttributesInterface
    {
        return $this->attributes;
    }

    public function getEpochNanos(): int
    {
        return $this->event->getEpochNanos();
    }

    public function getTotalAttributeCount(): int
    {
        return $this->event->getTotalAttributeCount();
    }
}
