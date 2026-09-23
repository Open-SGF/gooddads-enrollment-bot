<?php

declare(strict_types=1);

namespace App\Telemetry;

use ArrayIterator;
use IteratorAggregate;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use Traversable;

/** @implements IteratorAggregate<string, mixed> */
final readonly class RedactedAttributes implements AttributesInterface, IteratorAggregate
{
    /** @param array<string, mixed> $values */
    // @phpstan-ignore missingType.iterableValue (SDK interface extends Traversable without a value type)
    public function __construct(private AttributesInterface $original, private array $values) {}

    public function has(string $name): bool
    {
        return $this->original->has($name);
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function getDroppedAttributesCount(): int
    {
        return $this->original->getDroppedAttributesCount();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }
}
