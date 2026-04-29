<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

#[MapInputName(CamelCaseMapper::class)]
final class RegionData extends Data
{
    public function __construct(
        public string $id,
        public string $description,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}
}
