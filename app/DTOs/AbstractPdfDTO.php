<?php

namespace App\DTOs;

use App\Contracts\PdfDTO;
use Illuminate\Support\Facades\Log;

abstract readonly class AbstractPdfDTO implements PdfDTO
{
    abstract protected function mandatoryFields(): array;

    public function getMissingFields(): array
    {
        return array_values(array_filter(
            $this->mandatoryFields(),
            fn($field) => empty($this->$field)
        ));
    }

    public function hasMissingFields(): bool
    {
        return !empty($this->getMissingFields());
    }

    protected static function logMissing(string $dtoClass, array $mapped, array $mandatory): void
    {
        $missing = array_filter($mandatory, fn($f) => empty($mapped[$f]));

        if (!empty($missing)) {
            Log::warning("{$dtoClass}: missing mandatory fields", [
                'fields' => array_values($missing),
            ]);
        }
    }
}

?>