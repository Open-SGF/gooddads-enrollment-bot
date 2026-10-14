<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\ParticipantPdfVersion;
use Spatie\LaravelData\Data;

final class PdfGenerationResultDTO extends Data
{
    public function __construct(
        public string $filename,
        public string $contents,
        public ?ParticipantPdfVersion $version = null,
    ) {}

    /** @return list<string> */
    protected function mandatoryFields(): array
    {
        return ['filename', 'contents'];
    }
}
