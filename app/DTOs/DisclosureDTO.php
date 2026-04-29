<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class DisclosureDTO extends AbstractPdfDTO
{
    public function __construct(
        public string $fullName = '',
        public string $phone = '',
        public string $dob = '',
        public string $address = '',
        public string $email = '',
        public string $authorizeDys = '',
        public string $authorizeMhd = '',
        public string $authorizeDfas = '',
        public string $authorizeMmac = '',
        public string $authorizeOther = '',
        public string $authorizeCd = '',
        public string $authorizeDls = '',
        public string $discloseToAttorney = '',
        public string $discloseToLegislator = '',
        public string $discloseToEmployer = '',
        public string $discloseToGovernorsStaff = '',
        public string $purposeContinuityOfServicesCare = '',
        public string $purposeLegalConsultationRepresentation = '',
        public string $purposeComplaintInvestigationResolution = '',
        public string $purposeBackgroundInvestigation = '',
        public string $purposeLegalProceedings = '',
        public string $purposeTreatmentPlanning = '',
        public string $purposeAtConsumersRequest = '',
        public string $purposeToShareOrRefer = '',
        public string $purposeOther = '',
        public string $licensureInformation = '',
        public string $disclosureMedical = '',
        public string $hotlineInvestigations = '',
        public string $homeStudies = '',
        public string $eligibilityDeterminations = '',
        public string $substanceAbuseTreatment = '',
        public string $clientEmploymentRecords = '',
        public string $acceptTextMessages = '',
        public ?string $authorizeDiscloserFormOther = null,
    ) {}

    /** @return array<string, string|null> */
    public function toPdfArray(): array
    {
        return [
            'authorize_full_name' => $this->fullName,
            'authorize_dys' => $this->authorizeDys,
            'authorize_mhd' => $this->authorizeMhd,
            'authorize_dfas' => $this->authorizeDfas,
            'authorize_mmac' => $this->authorizeMmac,
            'authorize_other' => $this->authorizeOther,
            'authorize_discloser_form_other' => $this->authorizeDiscloserFormOther,
            'authorize_cd' => $this->authorizeCd,
            'authorize_dls' => $this->authorizeDls,
            'disclose_full_name' => $this->fullName,
            'disclose_phone' => $this->phone,
            'disclose_dob' => $this->dob,
            'disclose_address' => $this->address,
            'disclose_email' => $this->email,
            'disclose_to_attorney' => $this->discloseToAttorney,
            'disclose_to_legislator' => $this->discloseToLegislator,
            'disclose_to_employer' => $this->discloseToEmployer,
            'disclose_to_governors_staff' => $this->discloseToGovernorsStaff,
            'disclosure_purpose_continuity_of_services_care' => $this->purposeContinuityOfServicesCare,
            'disclosure_purpose_legal_consultation_representation' => $this->purposeLegalConsultationRepresentation,
            'disclosure_purpose_complaint_investigation_resolution' => $this->purposeComplaintInvestigationResolution,
            'disclosure_purpose_background_investigation' => $this->purposeBackgroundInvestigation,
            'disclosure_purpose_legal_proceedings' => $this->purposeLegalProceedings,
            'disclosure_purpose_treatment_planning' => $this->purposeTreatmentPlanning,
            'disclosure_purpose_at_consumers_request' => $this->purposeAtConsumersRequest,
            'disclosure_purpose_to_share_or_refer' => $this->purposeToShareOrRefer,
            'disclosure_licensure_information' => $this->licensureInformation,
            'disclosure_medical' => $this->disclosureMedical,
            'disclose_hotline_investigations' => $this->hotlineInvestigations,
            'disclosure_home_studies' => $this->homeStudies,
            'disclosure_eligibility_determinations' => $this->eligibilityDeterminations,
            'disclosure_substance_abuse_treatment' => $this->substanceAbuseTreatment,
            'disclosure_client_employment_records' => $this->clientEmploymentRecords,
            'accept_text_messages' => $this->acceptTextMessages,
        ];
    }

    /** @return list<string> */
    protected function mandatoryFields(): array
    {
        return [
            'fullName',
            'phone',
            'dob',
            'address',
            'email',
        ];
    }
}
