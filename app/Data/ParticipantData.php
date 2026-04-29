<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Ethnicity;
use App\Enums\MaritalStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

#[MapInputName(CamelCaseMapper::class)]
final class ParticipantData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $regionId,
        public readonly string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly string $city,
        public readonly string $state,
        public readonly string $zipcode,
        public readonly ?string $employer,
        public readonly ?string $cellPhoneNumber,
        public readonly ?string $homePhoneNumber,
        public readonly ?string $workPhoneNumber,
        public readonly ?string $altContactNumber,
        public readonly MaritalStatus $maritalStatus,
        public readonly Ethnicity $ethnicity,
        public readonly ?string $tShirtSize,
        public readonly ?string $probationParoleCaseWorkerName,
        public readonly ?string $probationParoleCaseWorkerPhone,
        public readonly ?string $participantPhoto,
        public readonly ?Carbon $intakeDate,
        public readonly Carbon $createdAt,
        public readonly Carbon $updatedAt,
    ) {}
}
