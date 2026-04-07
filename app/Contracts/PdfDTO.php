<?php

namespace App\Contracts;

interface PdfDTO extends PdfArrayable
{
    public function getMissingFields(): array;
    public function hasMissingFields(): bool;
}

?>