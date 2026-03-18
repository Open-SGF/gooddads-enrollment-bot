<?php

namespace App\DTOs;

readonly class ServicePlanDTO implements PdfArrayable
{
    public function __construct(
        public readonly string $participantFullName,
        public readonly string $clientNumber,
        public readonly string $goal,                          // service_plan_goal
        public readonly string $serviceIdentified,
        public readonly string $strategies_1,
        public readonly string $personResponsible_1,
        public readonly string $timeline_1,
        public readonly string $measureOfSuccess_1,
        public readonly string $strategies_2,
        public readonly string $personResponsible_2,
        public readonly string $timeline_2,
        public readonly string $measureOfSuccess_2,
        public readonly string $strategies_3,
        public readonly string $personResponsible_3,
        public readonly string $timeline_3,
        public readonly string $measureOfSuccess_3,
    ) {}

    public function toPdfArray(): array {
        return [
            'service_plan_participant_full_name'                => $this->participantFullName,
            'service_plan_client_number'                        => $this->clientNumber,
            'service_plan_goal'                                 => $this->goal,
            'service_plan_service_identified'                   => $this->serviceIdentified,
            'service_plan_strategies_1'                         => $this->strategies_1,
            'service_plan_person_responsible_1'                 => $this->personResponsible_1,
            'service_plan_timeline_1'                           => $this->timeline_1,
            'service_plan_measure_of_success_1'                 => $this->measureOfSuccess_1,
            'service_plan_strategies_2'                         => $this->strategies_2,
            'service_plan_person_responsible_2'                 => $this->personResponsible_2,
            'service_plan_timeline_2'                           => $this->timeline_2,
            'service_plan_measure_of_success_2'                 => $this->measureOfSuccess_2,
            'service_plan_strategies_3'                         => $this->strategies_3,
            'service_plan_person_responsible_3'                 => $this->personResponsible_3,
            'service_plan_timeline_3'                           => $this->timeline_3,
            'service_plan_measure_of_success_3'                 => $this->measureOfSuccess_3
        ];
    }
}
?>
