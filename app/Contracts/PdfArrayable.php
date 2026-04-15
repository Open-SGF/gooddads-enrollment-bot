<?php

declare(strict_types=1);

namespace App\Contracts;

interface PdfArrayable
{
    public function toPdfArray(): array;
}

?>
