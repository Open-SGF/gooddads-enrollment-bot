<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ChildDTO extends AbstractPdfDTO
{
    public function __construct(
        public string $name = '',
        public string $age = '',
        public string $dob = '',
    ) {}

    /** @return array<string, string> */
    public function toPdfArray(): array
    {
        return [
            'name' => $this->name,
            'age' => $this->age,
            'dob' => $this->dob,
        ];
    }

    /** @return list<string> */
    protected function mandatoryFields(): array
    {
        return ['name', 'age', 'dob'];
    }
}
