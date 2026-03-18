<?php

namespace App\Services;

use App\DTOs\ParticipantUpdateData;
use App\DTOs\ContactInfoDTO;
use App\DTOs\ChildDTO;
use App\DTOs\DisclosureDTO;
use App\DTOs\AssessmentDTO;
use App\DTOs\SurveyDTO;
use App\DTOs\ServicePlanDTO;
use Carbon\Carbon;

final class NeonDTOTransformer
{
    private function __construct(){}

    public static function transformParticipantData(array $participantData): ParticipantUpdateData {
        $contactInfo = $participantData['contactInfo']['records'][0];

        return new ParticipantUpdateData(
            id:             $contactInfo['persons_id']['value'],            
            firstName:      $contactInfo['firstName']['displayValue'] ?? '',
            lastName:       $contactInfo['lastName']['displayValue'] ?? '',
            contactInfo:    self::transformContactInfo($contactInfo),
            children:       self::transformChildren($participantData['children']['records'] ?? []),
            disclosure:     self::transformDisclosure($participantData['disclosure']['records'][0]),
            assessment:     self::transformAssessment($participantData['assessment']['records'][0]),
            survey:         self::transformSurvey($participantData['survey']['records'][0]),
            servicePlan:    self::transformServicePlan($participantData['servicePlan']['records'][0])
        );

    }

    private static function transformContactInfo(array $contactInfo): ContactInfoDTO
    {
        return new ContactInfoDTO(            
            titleRegion:            $contactInfo['regions_id']['displayValue'] ?? '',
            fullName:               $contactInfo['persons_id']['displayValue'] ?? '',
            enteredDate:            self::parseDateString($contactInfo['enteredDate']['value'] ?? null),
            address:                self::buildAddress($contactInfo),
            employer:               $contactInfo['employer']['value'] ?? '',
            tshirtSize:             $contactInfo['tShirtSize']['displayValue'] ?? '',
            phone:                  $contactInfo['homeCellPhone']['value'] ?? '',
            workPhone:              $contactInfo['workPhone']['value'] ?? '',
            otherPhone:             $contactInfo['otherNumber']['value'] ?? '',
            email:                  $contactInfo['email']['value'] ?? '',
            caseworkerName:         $contactInfo['probationParoleCaseWorkerName']['value'] ?? '',
            caseworkerPhone:        $contactInfo['probationParoleCaseWorkerPhone']['value'] ?? '',
            monthlyChildSupport:    $contactInfo['monthlyChildSupportPayment']['displayValue'] ?? '',
            maritalStatus:          $contactInfo['maritalStatus']['displayValue'] ?? '',
            ethnicity:              $contactInfo['ethnicity']['displayValue'] ?? '',
            contactWithChildren:    self::yesNo($contactInfo['contactWithChildren']['displayValue'] ?? null),
            childrenCustody:        self::inList($contactInfo['contactType']['value'] ?? '', '763'),
            childrenVisitation:     self::inList($contactInfo['contactType']['value'] ?? '', '762'),
            childrenPhone:          self::inList($contactInfo['contactType']['value'] ?? '', '1483')
        );
    }
   

    /** @return ChildDTO[]    */
    private static function transformChildren(array $children): array
    {
        $result = [];
        foreach ($children as $child) {
            $dob = self::parseDate($child['dateOfBirth']['value'] ?? null);
            $result[] = new ChildDTO(
                name: trim(($child['firstName']['value'] ?? '') . ' ' . ($child['lastName']['value'] ?? '')),
                age:  $dob ? (string) $dob->diffInYears(Carbon::now()) : '',
                dob:  $dob ? $dob->format('m/d/Y') : '',
            );
        }
        return $result;
    }

    private static function transformDisclosure(array $d): DisclosureDTO
    {
        $divisions = explode(',', $d['division']['value'] ?? '');
        $releaseTo = explode(',', $d['releaseTo']['value'] ?? '');
        $purposes  = explode(',', $d['purposeOfDisclosure']['value'] ?? '');
        $disclosed = explode(',', $d['informationToBeDisclosed']['value'] ?? '');

        return new DisclosureDTO(
            fullName:                                           $d['persons_id']['displayValue'] ?? '',
            phone:                                              $d['homeCellPhone']['value'] ?? '',
            dob:                                                self::parseDateString($d['dateOfBirth']['value'] ?? null),
            ## We should not collect this information
            // ssn:                                                null,
            address:                                            $d['fullAddress']['displayValue'] ?? '',
            email:                                              $d['email']['value'] ?? '',
            authorizeDys:                                       self::inArray('679', $divisions),
            authorizeMhd:                                       self::inArray('684', $divisions),
            authorizeDfas:                                      self::inArray('683', $divisions),
            authorizeMmac:                                      self::inArray('1484', $divisions),
            authorizeOther:                                     isset($d['divisionOther']['value']) && $d['divisionOther']['value'] ? 'Yes' : 'Off',
            authorizeDiscloserFormOther:                        $d['divisionOther']['value'] ?? null,
            authorizeCd:                                        self::inArray('682', $divisions),
            authorizeDls:                                       self::inArray('681', $divisions),
            ## This is the text field
            // disclose_to_attorney:                               $attorneyInList,
            discloseToAttorney:                                 "Neon has the checkbox value, but not associated text; we have no checkbox field, but the text",
            ## This is the text field
            // disclose_to_legislator:                             $this->inArray('1487', $releaseTo),
            discloseToLegislator:                               "Neon has the checkbox value, but not associated text; we have no checkbox field, but the text",
            ## This is the text field
            // disclose_to_employer:                               $this->inArray('1486', $releaseTo),
            discloseToEmployer:                                 "Neon has the checkbox value, but not associated text; we have no checkbox field, but the text",
            ## This is the text field
            // disclose_to_governors_staff:                        $this->inArray('1488', $releaseTo),
            discloseToGovernorsStaff:                           "Neon has the checkbox value, but not associated text; we have no checkbox field, but the text",
            ## Pre-filled
            // other_discloser:                                    $d['releaseToOther']['displayValue'] ?? '',
            ## Pre-filled
            // purpose_eligibility_determination:                  $this->inArray('585', $purposes),
            ## Pre-filled
            // purpose_employment:                                 $this->inArray('594', $purposes),
            purposeContinuityOfServicesCare:                    self::inArray('447', $purposes),
            purposeLegalConsultationRepresentation:             self::inArray('1490', $purposes),
            purposeComplaintInvestigationResolution:            self::inArray('1491', $purposes),
            purposeBackgroundInvestigation:                     self::inArray('1492', $purposes),
            purposeLegalProceedings:                            self::inArray('1493', $purposes),
            purposeTreatmentPlanning:                           self::inArray('1494', $purposes),
            purposeAtConsumersRequest:                          self::inArray('1495', $purposes),
            purposeToShareOrRefer:                              self::inArray('755', $purposes),
            //This is the checkbox for the 'other purpose' field which is pre-filled, but the box is not checked, hence 'Yes' here
            purposeOther:                                       "Yes", //$this->inArray('1496', $purposes),
            licensureInformation:                               self::inArray('161', $disclosed),
            disclosureMedical:                                  self::inArray('1497', $disclosed),
            hotlineInvestigations:                              self::inArray('1499', $disclosed),
            homeStudies:                                        self::inArray('1500', $disclosed),
            eligibilityDeterminations:                          self::inArray('1501', $disclosed),
            substanceAbuseTreatment:                            self::inArray('1502', $disclosed),
            clientEmploymentRecords:                            self::inArray('1503', $disclosed),
            acceptTextMessages:                                 self::yesNo($d['acceptsTextMessage']['displayValue'] ?? null),
        );
    }

    private static function transformAssessment(array $a): AssessmentDTO
    {
        $otherValue = $a['other']['displayValue'] ?? null;

        return new AssessmentDTO(
            fullName:                               $a['persons_id']['displayValue'] ?? '',
            dob:                                    $a['dateOfBirth']['displayValue'] ?? '',
            ## We should not collect this information
            // ssn:                                    null,
            eligibilityMissouriResident:            self::yesNo($a['missouriResident']['displayValue'] ?? null),
            eligibilityChildUnder18:                self::yesNo($a['childUnder18']['displayValue'] ?? null),
            financialEligibility:                   'Off', // completed by state agency, not in Neon
            financialDriversLicence:                self::yesNo($a['dL']['displayValue'] ?? null),
            financialUtilityBill:                   self::yesNo($a['utilityBill']['displayValue'] ?? null),
            financialWrittenEmployerStatement:      self::yesNo($a['writtenEmployerStatement']['displayValue'] ?? null),
            financialSsBenefitsStatement:           self::yesNo($a['socialSecurityBenefitsStatement']['displayValue'] ?? null),
            financialNoEmploymentIncome:            self::yesNo($a['selfAttestationOfNoEmploymentOrIncome']['displayValue'] ?? null),
            financialUnemploymentCompensation:      self::yesNo($a['unemploymentCompensation']['displayValue'] ?? null),
            financialOther:                         $otherValue ? 'Yes' : 'Off',
            financialOtherDescription:              $otherValue ?: null,
            povertyMonthlyIncome:                   $a['hoseholdIncome']['displayValue'] ?? '',  // typo is in Neon field name
            povertyHouseholdMembers:                $a['numberOfFamilyMembersInHousehold']['value'] ?? '',
            povertyPercentageFpl:                   $a['percentageOfFPL']['value'] ?? '',
        );
    }

    private static function transformSurvey(array $s): SurveyDTO
    {
        $reasons       = explode(',', $s['reasons']['value'] ?? '');
        $howHeardAbout = explode(',', $s['hearAboutUs']['value'] ?? '');
        $expectedGain  = explode(',', $s['expectToGain']['value'] ?? '');

        return new SurveyDTO(
            clientDob:      $s['dateOfBirth']['displayValue'] ?? '',
            deliveryMethod: '', // not in Neon — filled in by participant on paper
            why:            match(true) {
                self::inArray('453',  $reasons) => 'Responsible father',
                self::inArray('454',  $reasons) => 'Referred',
                self::inArray('1506', $reasons) => 'Child support concerns',
                self::inArray('695',  $reasons) => 'Attourney',  // matches PDF FieldStateOption spelling
                self::inArray('1507', $reasons) => 'Other',
                default => 'Off',
            },
            whyOther:       $s['reasonsOther']['value'] ?? '',
            how:            match(true) {
                self::inArray('1510', $howHeardAbout) => 'Family support',
                self::inArray('1509', $howHeardAbout) => 'Past participant',
                self::inArray('1512', $howHeardAbout) => 'Marketing',
                self::inArray('1511', $howHeardAbout) => 'Prosecuting attorney',
                self::inArray('1513', $howHeardAbout) => 'The organization',
                self::inArray('1508', $howHeardAbout) => 'Word of mouth',
                self::inArray('1514', $howHeardAbout) => 'Other',
                default => 'Off',
            },
            howOther:       $s['hearAboutUsOther']['value'] ?? '',
            gain:           match(true) {
                self::inArray('1520', $expectedGain) => 'Access to mentors',
                self::inArray('1524', $expectedGain) => 'Credit repair assistance',
                self::inArray('1521', $expectedGain) => 'Criminal History Assistance',
                self::inArray('1522', $expectedGain) => 'Overcoming homelessness assistance',
                self::inArray('1516', $expectedGain) => 'Abuse assistance',
                self::inArray('1523', $expectedGain) => 'Visitation custody assistance',
                self::inArray('1515', $expectedGain) => 'Emplyment opportunities',  // matches PDF FieldStateOption spelling
                self::inArray('1517', $expectedGain) => 'Parenting skills',
                self::inArray('1526', $expectedGain) => 'Increased Understanding of Child Support',
                self::inArray('1525', $expectedGain) => 'Maintaining Hope',
                self::inArray('1518', $expectedGain) => 'Resume building',
                self::inArray('1519', $expectedGain) => 'Legal services',
                self::inArray('1527', $expectedGain) => 'Other',
                default => 'Off',
            },
            gainOther:      $s['expectToGainOther']['value'] ?? '',
        );
    }

    private static function transformServicePlan(array $sp): ServicePlanDTO
    {
        return new ServicePlanDTO(
            participantFullName:    $sp['persons_id']['displayValue'] ?? '',
            clientNumber:           $sp['clientNumber']['value'] ?? '',
            goal:                   '', // database appears to be missing this field per original comment
            serviceIdentified:      $sp['serviceIdentifiedByTheParticipants']['value'] ?? '',
            strategies_1:           $sp['goals_custodyVisitationObj']['displayValue'] ?? '',
            personResponsible_1:    $sp['goals_custodyVisitationPersonRes']['displayValue'] ?? '',
            timeline_1:             $sp['goals_custodyVisitationTimeline']['displayValue'] ?? '',
            measureOfSuccess_1:     $sp['goals_custodyVisitationMeasure']['value'] ?? '',
            strategies_2:           $sp['goals_educationEmploymentObj']['displayValue'] ?? '',
            personResponsible_2:    $sp['goals_educationEmploymentPersonRes']['displayValue'] ?? '',
            timeline_2:             $sp['goals_educationEmploymentTimeline']['displayValue'] ?? '',
            measureOfSuccess_2:     $sp['goals_educationEmploymentMeasure']['value'] ?? '',
            strategies_3:           $sp['goals_housingTransportationObj']['displayValue'] ?? '',
            personResponsible_3:    $sp['goals_housingTransportationPersonRes']['displayValue'] ?? '',
            timeline_3:             $sp['goals_housingTransportationTimeline']['displayValue'] ?? '',
            measureOfSuccess_3:     $sp['goals_housingTransportationMeasure']['value'] ?? '',
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function parseDate(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('Y-m-d', $value) : null;
    }

    private static function parseDateString(?string $value): string
    {
        if (!$value) return '';
        $date = Carbon::createFromFormat('Y-m-d', $value);
        return $date ? $date->format('m/d/Y') : '';
    }

    private static function buildAddress(array $c): string
    {
        return trim(implode(' ', array_filter([
            $c['address1']['value'] ?? '',
            $c['address2']['value'] ?? '',
            $c['city']['value'] ?? '',
            $c['state']['displayValue'] ?? '',
            $c['zip']['value'] ?? '',
        ])));
    }

    private static function yesNo(?string $value): string
    {
        return match($value) {
            '1'     => 'Yes',
            '0'     => 'No',
            default => 'Off',
        };
    }

    private static function inList(string $list, string $id): string
    {
        return in_array($id, explode(',', $list)) ? 'Yes' : 'Off';
    }

    private static function inArray(string $id, array $arr): string
    {
        return in_array($id, $arr) ? 'Yes' : 'Off';
    }
}