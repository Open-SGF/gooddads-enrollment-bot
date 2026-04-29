<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class SurveyDTO extends AbstractPdfDTO
{
    public function __construct(
        public string $clientDob = '',
        public string $deliveryMethod = '',
        public string $why = '',
        public string $whyOther = '',
        public string $how = '',
        public string $howOther = '',
        public string $gain = '',
        public string $gainOther = '',
    ) {}

    /** @return array<string, string> */
    public function toPdfArray(): array
    {
        return [
            'survey_client_dob' => $this->clientDob,
            'survey_delivery_method' => $this->deliveryMethod,
            'survey_why' => $this->why,
            'survey_other_description' => $this->whyOther,
            'survey_how' => $this->how,
            'survey_how_other_description' => $this->howOther,
            'survey_gain' => $this->gain,
            'survey_gain_other_description' => $this->gainOther,
        ];
    }

    /** @return list<string> */
    protected function additionalMissingFields(): array
    {
        $missing = [];

        if (($this->why === '' || $this->why === '0') && ($this->whyOther === '' || $this->whyOther === '0')) {
            $missing[] = 'why (or whyOther)';
        }

        if (($this->how === '' || $this->how === '0') && ($this->howOther === '' || $this->howOther === '0')) {
            $missing[] = 'how (or howOther)';
        }

        if (($this->gain === '' || $this->gain === '0') && ($this->gainOther === '' || $this->gainOther === '0')) {
            $missing[] = 'gain (or gainOther)';
        }

        return $missing;
    }

    /** @return list<string> */
    protected function mandatoryFields(): array
    {
        return [];
    }
}
