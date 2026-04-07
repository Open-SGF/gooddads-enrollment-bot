<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SurveyDTO extends AbstractPdfDTO
{
    public function __construct(
        public string $clientDob       = '',
        public string $deliveryMethod  = '',
        public string $why             = '',
        public string $whyOther        = '',
        public string $how             = '',
        public string $howOther        = '',
        public string $gain            = '',
        public string $gainOther       = '',
    ) {}

    protected function mandatoryFields(): array
    {
        return [
            'clientDob',
            'deliveryMethod',
        ];
    }

    public function toPdfArray(): array
    {
        return [
            'survey_client_dob'              => $this->clientDob,
            'survey_delivery_method'         => $this->deliveryMethod,
            'survey_why'                     => $this->why,
            'survey_other_description'       => $this->whyOther,
            'survey_how'                     => $this->how,
            'survey_how_other_description'   => $this->howOther,
            'survey_gain'                    => $this->gain,
            'survey_gain_other_description'  => $this->gainOther,
        ];
    }
}

?>