<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Contracts\PdfDTO;
use Illuminate\Support\Facades\Log;

abstract readonly class AbstractPdfDTO implements PdfDTO
{
    /** @return list<string> */
    abstract protected function mandatoryFields(): array;

    /** @return list<string> */
    final public function getMissingFields(): array
    {
        $missing = array_values(array_filter(
            $this->mandatoryFields(),
            fn (string $field): bool => empty($this->$field)
        ));

        return array_merge($missing, $this->additionalMissingFields());
    }

    final public function hasMissingFields(): bool
    {
        return $this->getMissingFields() !== [];
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @param  list<string>  $mandatory
     */
    protected static function logMissing(string $dtoClass, array $mapped, array $mandatory): void
    {
        $missing = array_filter($mandatory, fn (string $field): bool => empty($mapped[$field]));

        if ($missing !== []) {
            Log::warning($dtoClass.': missing mandatory fields', [
                'fields' => array_values($missing),
            ]);
        }
    }

    /** @return list<string> */
    protected function additionalMissingFields(): array
    {
        return [];
    }
}
