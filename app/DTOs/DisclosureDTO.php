<?php

namespace App\DTOs;

readonly class DisclosureDTO implements PdfArrayable
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $phone,
        public readonly string $dob,
        public readonly string $address,
        public readonly string $email,
        public readonly string $authorizeDys,
        public readonly string $authorizeMhd,
        public readonly string $authorizeDfas,
        public readonly string $authorizeMmac,
        public readonly string $authorizeOther,
        public readonly ?string $authorizeDiscloserFormOther,
        public readonly string $authorizeCd,
        public readonly string $authorizeDls,
        public readonly string $discloseToAttorney,      
        public readonly string $discloseToLegislator,
        public readonly string $discloseToEmployer,
        public readonly string $discloseToGovernorsStaff,
        public readonly string $purposeContinuityOfServicesCare,
        public readonly string $purposeLegalConsultationRepresentation,
        public readonly string $purposeComplaintInvestigationResolution, 
        public readonly string $purposeBackgroundInvestigation,
        public readonly string $purposeLegalProceedings,
        public readonly string $purposeTreatmentPlanning,
        public readonly string $purposeAtConsumersRequest,
        public readonly string $purposeToShareOrRefer,
        public readonly string $purposeOther,
        public readonly string $licensureInformation,
        public readonly string $disclosureMedical,             
        public readonly string $hotlineInvestigations,
        public readonly string $homeStudies,
        public readonly string $eligibilityDeterminations,
        public readonly string $substanceAbuseTreatment,
        public readonly string $clientEmploymentRecords,
        public readonly string $acceptTextMessages,
    ) {}

    public function toPdfArray(): array {
        return [
            'authorize_full_name'                                   => $this->fullName,
            'authorize_dys'                                         => $this->authorizeDys,
            'authorize_mhd'                                         => $this->authorizeMhd,
            'authorize_dfas'                                        => $this->authorizeDfas,
            'authorize_mmac'                                        => $this->authorizeMmac,
            'authorize_other'                                       => $this->authorizeOther,
            'authorize_discloser_form_other'                        => $this->authorizeDiscloserFormOther,
            'authorize_cd'                                          => $this->authorizeCd,
            'authorize_dls'                                         => $this->authorizeDls,
            'disclose_full_name'                                    => $this->fullName,
            'disclose_phone'                                        => $this->phone,
            'disclose_dob'                                          => $this->dob,
            'disclose_address'                                      => $this->address,
            'disclose_email'                                        => $this->email,
            'disclose_to_attorney'                                  => $this->discloseToAttorney,
            'disclose_to_legislator'                                => $this->discloseToLegislator,
            'disclose_to_employer'                                  => $this->discloseToEmployer,
            'disclose_to_governors_staff'                           => $this->discloseToGovernorsStaff,
            'disclosure_purpose_continuity_of_services_care'        => $this->purposeContinuityOfServicesCare,
            'disclosure_purpose_legal_consultation_representation'  => $this->purposeLegalConsultationRepresentation,
            'disclosure_purpose_complaint_investigation_resolution' => $this->purposeComplaintInvestigationResolution,
            'disclosure_purpose_background_investigation'           => $this->purposeBackgroundInvestigation,
            'disclosure_purpose_legal_proceedings'                  => $this->purposeLegalProceedings,
            'disclosure_purpose_treatment_planning'                 => $this->purposeTreatmentPlanning,
            'disclosure_purpose_at_consumers_request'               => $this->purposeAtConsumersRequest,
            'disclosure_purpose_to_share_or_refer'                  => $this->purposeToShareOrRefer,
            'disclosure_licensure_information'                      => $this->licensureInformation,
            'disclosure_medical'                                    => $this->disclosureMedical,
            'disclose_hotline_investigations'                       => $this->hotlineInvestigations,
            'disclosure_home_studies'                               => $this->homeStudies,
            'disclosure_eligibility_determinations'                 => $this->eligibilityDeterminations,
            'disclosure_substance_abuse_treatment'                  => $this->substanceAbuseTreatment,
            'disclosure_client_employment_records'                  => $this->clientEmploymentRecords,
            'accept_text_messages'                                  => $this->acceptTextMessages
        ];
    } 
}
 ?>