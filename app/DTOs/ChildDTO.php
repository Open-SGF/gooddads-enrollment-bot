<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ChildDTO extends AbstractPdfDto
{
    public function __construct(
        public string $name = '',
        public string $age  = '',
        public string $dob  = '',
    ) {}

    protected function mandatoryFields(): array
    {
        return ['name', 'age', 'dob'];
    }

    public function toPdfArray(): array
    {
        return [
            'name' => $this->name,
            'age'  => $this->age,
            'dob'  => $this->dob,
        ];
    }
}