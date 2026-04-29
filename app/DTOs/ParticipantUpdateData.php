<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Facades\Log;

final readonly class ParticipantUpdateData
{
    /**
     * @param  ChildDTO[]  $children
     */
    public function __construct(
        // Meta (not PDF fields, used for file generation)
        public string $id,
        public string $firstName,
        public string $lastName,

        // Enrollment form data
        public ContactInfoDTO $contactInfo,
        public array $children,
        public DisclosureDTO $disclosure,
        public AssessmentDTO $assessment,
        public SurveyDTO $survey,
        public ServicePlanDTO $servicePlan
    ) {}

    /**
     * This is for the email generation
     */
    public function fullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    /** @return array<string, string|null> */
    public function toPdfArray(): array
    {

        $children = [];

        foreach ($this->children as $index => $child) {
            $adjusted_index = $index + 1;
            $children['child_name_'.$adjusted_index] = $child->name;
            $children['child_age_'.$adjusted_index] = $child->age;
            $children['child_dob_'.$adjusted_index] = $child->dob;

        }

        $arrays = [
            $this->contactInfo->toPdfArray(),
            $children,
            $this->disclosure->toPdfArray(),
            $this->assessment->toPdfArray(),
            $this->survey->toPdfArray(),
            $this->servicePlan->toPdfArray(),
        ];

        return array_merge(...$arrays);
    }

    /** @return array<string, list<string>> */
    public function getMissingFields(): array
    {
        $missing = [];

        $dtos = [
            'contactInfo' => $this->contactInfo,
            'disclosure' => $this->disclosure,
            'assessment' => $this->assessment,
            'survey' => $this->survey,
            'servicePlan' => $this->servicePlan,
        ];

        foreach ($dtos as $name => $dto) {
            if ($dto->hasMissingFields()) {
                $missing[$name] = $dto->getMissingFields();
            }
        }

        if ($this->children === []) {
            $missing['children'] = ['children array is empty'];
        } else {
            foreach ($this->children as $index => $child) {
                if ($child->hasMissingFields()) {
                    $missing['child_'.$index] = $child->getMissingFields();
                }
            }
        }

        if ($missing !== []) {
            Log::warning('ParticipantUpdateData: missing fields detected', [
                'participant_id' => $this->id,
                'missing' => $missing,
            ]);
        }

        return $missing;
    }

    public function hasMissingFields(): bool
    {
        return $this->getMissingFields() !== [];
    }
}
