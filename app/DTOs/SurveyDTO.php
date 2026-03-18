<?php

namespace App\DTOs;

readonly class SurveyDTO
{
    public function __construct(
        public readonly string $clientDob,
        public readonly string $deliveryMethod,
        public readonly string $why,
        public readonly string $whyOther,
        public readonly string $how,
        public readonly string $howOther,
        public readonly string $gain,
        public readonly string $gainOther,
    ) {}

    public function toPdfArray(): array {
        return [
            'survey_client_dob'             => $this->clientDob,
            'survey_delivery_method'        => $this->deliveryMethod,
            'survey_why'                    => $this->why,
            'survey_other_description'      => $this->whyOther,
            'survey_how'                    => $this->how,
            'survey_how_other_description'  => $this->howOther,
            'survey_gain'                   => $this->gain,
            'survey_gain_other_description' => $this->gainOther
        ];
    }
}
?>