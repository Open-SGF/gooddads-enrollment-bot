<?php

declare(strict_types=1);

namespace App\DTOs;

final class ChildDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $age,
        public readonly string $dob,
    ) {}
}
