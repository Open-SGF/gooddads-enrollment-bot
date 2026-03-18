<?php

namespace App\DTOs;

readonly class AssessmentDTO implements PdfArrayable
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $dob,
        public readonly string $eligibilityMissouriResident,
        public readonly string $eligibilityChildUnder18,
        public readonly string $financialEligibility,         
        public readonly string $financialDriversLicence,
        public readonly string $financialUtilityBill,
        public readonly string $financialWrittenEmployerStatement,
        public readonly string $financialSsBenefitsStatement,
        public readonly string $financialNoEmploymentIncome,
        public readonly string $financialUnemploymentCompensation,
        public readonly string $financialOther,
        public readonly ?string $financialOtherDescription,
        public readonly string $povertyMonthlyIncome,
        public readonly string $povertyHouseholdMembers,
        public readonly string $povertyPercentageFpl,        
    ) {}

    public function toPdfArray(): array {
        return [
            'participant_full_name'                             => $this->fullName,
            'participant_dob'                                   => $this->dob,
            'eligibility_missouri_resident'                     => $this->eligibilityMissouriResident,
            'eligibility_child_under_18'                        => $this->eligibilityChildUnder18,
            'financial_assessment_eligibility'                  => $this->financialEligibility,
            'financial_assessment_drivers_licence'              => $this->financialDriversLicence,
            'financial_assessment_utility_bill'                 => $this->financialUtilityBill,
            'financial_assessment_written_employer_statement'   => $this->financialWrittenEmployerStatement,
            'financial_assessment_ss_benefits_statement'        => $this->financialSsBenefitsStatement,
            'financial_assessment_no_employment_income'         => $this->financialNoEmploymentIncome,
            'financial_assessment_unemployment_compensation'    => $this->financialUnemploymentCompensation,
            'financial_assessment_other'                        => $this->financialOther,
            'financial_assessment_other_description'            => $this->financialOtherDescription,
            'poverty_level_monthly_income'                      => $this->povertyMonthlyIncome,
            'poverty_level_number_of_household_members'         => $this->povertyHouseholdMembers,
            'poverty_level_percentage_fpl'                      => $this->povertyPercentageFpl
        ];
    }
}
?>
