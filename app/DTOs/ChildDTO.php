<?php

namespace App\DTOs;

use App\Contracts\PdfArrayable;

class ChildDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $age,
        public readonly string $dob,
    ) {}
}
?>