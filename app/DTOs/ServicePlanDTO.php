<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ServicePlanDTO extends AbstractPdfDTO
{
    public function __construct(
        public string $participantFullName  = '',
        public string $clientNumber         = '',
        public string $goal                 = '',
        public string $serviceIdentified    = '',
        public string $strategies1          = '',
        public string $personResponsible1   = '',
        public string $timeline1            = '',
        public string $measureOfSuccess1    = '',
        public string $strategies2          = '',
        public string $personResponsible2   = '',
        public string $timeline2            = '',
        public string $measureOfSuccess2    = '',
        public string $strategies3          = '',
        public string $personResponsible3   = '',
        public string $timeline3            = '',
        public string $measureOfSuccess3    = '',
    ) {}

    protected function mandatoryFields(): array
    {
        return [
            'participantFullName',
            'clientNumber',
            'goal',
            'serviceIdentified',
        ];
    }

    public function toPdfArray(): array
    {
        return [
            'service_plan_participant_full_name'  => $this->participantFullName,
            'service_plan_client_number'          => $this->clientNumber,
            'service_plan_goal'                   => $this->goal,
            'service_plan_service_identified'     => $this->serviceIdentified,
            'service_plan_strategies_1'           => $this->strategies1,
            'service_plan_person_responsible_1'   => $this->personResponsible1,
            'service_plan_timeline_1'             => $this->timeline1,
            'service_plan_measure_of_success_1'   => $this->measureOfSuccess1,
            'service_plan_strategies_2'           => $this->strategies2,
            'service_plan_person_responsible_2'   => $this->personResponsible2,
            'service_plan_timeline_2'             => $this->timeline2,
            'service_plan_measure_of_success_2'   => $this->measureOfSuccess2,
            'service_plan_strategies_3'           => $this->strategies3,
            'service_plan_person_responsible_3'   => $this->personResponsible3,
            'service_plan_timeline_3'             => $this->timeline3,
            'service_plan_measure_of_success_3'   => $this->measureOfSuccess3,
        ];
    }
}

?>