<?php

declare(strict_types=1);

namespace App\Concerns;

use BackedEnum as Enum;

/**
 * @mixin Enum
 */
trait EnumDisplayArray
{
    /**
     * @param  list<self>  $displayOnly
     * @return array<string, string>
     */
    public static function displayArray(array $displayOnly = []): array
    {
        $displayValues = $displayOnly !== [] ? $displayOnly : self::cases();
        $values = [];

        foreach ($displayValues as $case) {
            $values[$case->value] = $case->displayValue();
        }

        return $values;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function defaultDisplayValue(): string
    {
        return str($this->value)
            ->snake()
            ->replace('-', ' ')
            ->replace('_', ' ')
            ->title()
            ->value();
    }

    // this can be overridden in the enum class for specific names
    public function displayValue(): string
    {
        return $this->defaultDisplayValue();
    }
}
