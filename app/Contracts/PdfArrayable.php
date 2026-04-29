<?php

declare(strict_types=1);

namespace App\Contracts;

interface PdfArrayable
{
    /** @return array<string, string|null> */
    public function toPdfArray(): array;
}
