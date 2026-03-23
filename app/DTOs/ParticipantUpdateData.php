<?php

namespace App\DTOs;

readonly class ParticipantUpdateData
{
    /**
     * @param ChildDTO[] $children
     */
    public function __construct(
        // Meta (not PDF fields, used for file generation)
        public readonly int    $id,
        public readonly string $firstName,
        public readonly string $lastName,

        // Enrollment form data
        public readonly ContactInfoDTO $contactInfo,
        public readonly array $children,
        public readonly DisclosureDTO $disclosure,
        public readonly AssessmentDTO $assessment,
        public readonly SurveyDTO $survey,
        public readonly ServicePlanDTO $servicePlan        
    ) {}

    /**
     * This is for the email generation
     */
    public function fullName(): string {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function toPdfArray(): array {

        $children = [];

        foreach ($this->children as $index => $child) {
            $adjusted_index = $index + 1;
            $children['child_name_' . $adjusted_index] = $child->name;
            $children['child_age_' . $adjusted_index] = $child->age;
            $children['child_dob_' . $adjusted_index] = $child->dob;

        }

        $arrays = [
            $this->contactInfo->toPdfArray(),
            $children,
            $this->disclosure->toPdfArray(),
            $this->assessment->toPdfArray(),
            $this->survey->toPdfArray(),
            $this->servicePlan->toPdfArray()
        ];

        return array_merge(...$arrays);
    }
}
?>