<?php

namespace App\DTOs;

class ChildDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $age,
        public readonly string $dob,
    ) {}
}
?>