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
        return [];
    }

    public function getMissingFields(): array
    {
        $missing = parent::getMissingFields();

        if (empty($this->why) && empty($this->whyOther)) {
            $missing[] = 'why (or whyOther)';
        }

        if (empty($this->how) && empty($this->howOther)) {
            $missing[] = 'how (or howOther)';
        }

        if (empty($this->gain) && empty($this->gainOther)) {
            $missing[] = 'gain (or gainOther)';
        }

        return $missing;
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