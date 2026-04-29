<?php

declare(strict_types=1);

namespace App\Contracts;

interface PdfDTO extends PdfArrayable
{
    /** @return list<string> */
    public function getMissingFields(): array;

    public function hasMissingFields(): bool;
}
