<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class AssessmentDTO extends AbstractPdfDTO
{
    public function __construct(
        public string $fullName,
        public string $dob,
        public string $eligibilityMissouriResident,
        public string $eligibilityChildUnder18,
        public string $financialEligibility,
        public string $financialDriversLicence,
        public string $financialUtilityBill,
        public string $financialWrittenEmployerStatement,
        public string $financialSsBenefitsStatement,
        public string $financialNoEmploymentIncome,
        public string $financialUnemploymentCompensation,
        public string $financialOther,
        public ?string $financialOtherDescription = null,
        public string $povertyMonthlyIncome = '',
        public string $povertyHouseholdMembers = '',
        public string $povertyPercentageFpl = '',
    ) {}

    /** @return array<string, string|null> */
    public function toPdfArray(): array
    {
        return [
            'participant_full_name' => $this->fullName,
            'participant_dob' => $this->dob,
            'eligibility_missouri_resident' => $this->eligibilityMissouriResident,
            'eligibility_child_under_18' => $this->eligibilityChildUnder18,
            'financial_assessment_eligibility' => $this->financialEligibility,
            'financial_assessment_drivers_licence' => $this->financialDriversLicence,
            'financial_assessment_utility_bill' => $this->financialUtilityBill,
            'financial_assessment_written_employer_statement' => $this->financialWrittenEmployerStatement,
            'financial_assessment_ss_benefits_statement' => $this->financialSsBenefitsStatement,
            'financial_assessment_no_employment_income' => $this->financialNoEmploymentIncome,
            'financial_assessment_unemployment_compensation' => $this->financialUnemploymentCompensation,
            'financial_assessment_other' => $this->financialOther,
            'financial_assessment_other_description' => $this->financialOtherDescription,
            'poverty_level_monthly_income' => $this->povertyMonthlyIncome,
            'poverty_level_number_of_household_members' => $this->povertyHouseholdMembers,
            'poverty_level_percentage_fpl' => $this->povertyPercentageFpl,
        ];
    }

    /** @return list<string> */
    protected function mandatoryFields(): array
    {
        return [
            'fullName',
            'dob',
            'eligibilityMissouriResident',
            'eligibilityChildUnder18',
            'povertyMonthlyIncome',
            'povertyHouseholdMembers',
        ];
    }
}
