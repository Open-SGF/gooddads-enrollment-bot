<?php

declare(strict_types=1);

namespace App\Transformers;

use App\DTOs\AssessmentDTO;
use App\DTOs\ChildDTO;
use App\DTOs\ContactInfoDTO;
use App\DTOs\DisclosureDTO;
use App\DTOs\ParticipantUpdateData;
use App\DTOs\ServicePlanDTO;
use App\DTOs\SurveyDTO;
use App\Services\NeonApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * @phpstan-import-type NeonEnvelope from NeonApiService
 * @phpstan-import-type NeonRecord from NeonApiService
 */
final class NeonDTOTransformer
{
    private function __construct() {}

    /** @param array<string, NeonEnvelope> $participantData */
    public static function transformParticipantData(array $participantData): ParticipantUpdateData
    {
        $contactInfo = self::firstRecord($participantData['contactInfo']);

        return new ParticipantUpdateData(
            id: self::value($contactInfo, 'persons_id'),
            firstName: self::displayValue($contactInfo, 'firstName'),
            lastName: self::displayValue($contactInfo, 'lastName'),
            contactInfo: self::transformContactInfo($contactInfo),
            children: self::transformChildren($participantData['children']['records']),
            disclosure: self::transformDisclosure(self::firstRecord($participantData['disclosure'])),
            assessment: self::transformAssessment(self::firstRecord($participantData['assessment'])),
            survey: self::transformSurvey(self::firstRecord($participantData['survey'])),
            servicePlan: self::transformServicePlan(self::firstRecord($participantData['servicePlan']))
        );
    }

    /** @param NeonRecord $contactInfo */
    private static function transformContactInfo(array $contactInfo): ContactInfoDTO
    {
        return new ContactInfoDTO(
            titleRegion: self::displayValue($contactInfo, 'regions_id'),
            fullName: self::displayValue($contactInfo, 'persons_id'),
            enteredDate: self::parseDateString(self::nullableValue($contactInfo, 'enteredDate')),
            address: self::buildAddress($contactInfo),
            employer: self::nullableValue($contactInfo, 'employer'),
            tshirtSize: self::nullableDisplayValue($contactInfo, 'tShirtSize'),
            phone: self::nullableValue($contactInfo, 'homeCellPhone'),
            workPhone: self::nullableValue($contactInfo, 'workPhone'),
            otherPhone: self::nullableValue($contactInfo, 'otherNumber'),
            email: self::nullableValue($contactInfo, 'email'),
            caseworkerName: self::nullableValue($contactInfo, 'probationParoleCaseWorkerName'),
            caseworkerPhone: self::nullableValue($contactInfo, 'probationParoleCaseWorkerPhone'),
            monthlyChildSupport: self::nullableDisplayValue($contactInfo, 'monthlyChildSupportPayment'),
            maritalStatus: self::nullableDisplayValue($contactInfo, 'maritalStatus'),
            ethnicity: self::nullableDisplayValue($contactInfo, 'ethnicity'),
            contactWithChildren: self::yesNo(self::nullableDisplayValue($contactInfo, 'contactWithChildren')),
            childrenCustody: self::inList(self::value($contactInfo, 'contactType'), '763'),
            childrenVisitation: self::inList(self::value($contactInfo, 'contactType'), '762'),
            childrenPhone: self::inList(self::value($contactInfo, 'contactType'), '1483')
        );
    }

    /**
     * @param  list<NeonRecord>  $children
     * @return ChildDTO[]
     */
    private static function transformChildren(array $children): array
    {
        $result = [];
        foreach ($children as $child) {
            $dob = self::parseDate(self::nullableValue($child, 'dateOfBirth'));
            $ageInYears = $dob?->diffInYears(Date::now());

            $result[] = new ChildDTO(
                name: mb_trim(self::value($child, 'firstName').' '.self::value($child, 'lastName')),
                age: match (true) {
                    $ageInYears === null => '',
                    $ageInYears < 1 => 'Under 1',
                    default => (string) $ageInYears,
                },
                dob: $dob instanceof Carbon ? $dob->format('m/d/Y') : '',
            );
        }

        return $result;
    }

    /** @param NeonRecord $d */
    private static function transformDisclosure(array $d): DisclosureDTO
    {
        $divisions = explode(',', self::value($d, 'division'));
        $purposes = explode(',', self::value($d, 'purposeOfDisclosure'));
        $disclosed = explode(',', self::value($d, 'informationToBeDisclosed'));

        return new DisclosureDTO(
            fullName: self::displayValue($d, 'persons_id'),
            phone: self::value($d, 'homeCellPhone'),
            dob: self::parseDateString(self::nullableValue($d, 'dateOfBirth')),
            // # We should not collect this information
            // ssn:                                                null,
            address: self::displayValue($d, 'fullAddress'),
            email: self::value($d, 'email'),
            authorizeDys: self::inArray('679', $divisions),
            authorizeMhd: self::inArray('684', $divisions),
            authorizeDfas: self::inArray('683', $divisions),
            authorizeMmac: self::inArray('1484', $divisions),
            authorizeOther: self::value($d, 'divisionOther') !== '' ? 'Yes' : 'Off',
            authorizeCd: self::inArray('682', $divisions),
            authorizeDls: self::inArray('681', $divisions),
            // # This is the text field
            // disclose_to_attorney:                               $attorneyInList,
            discloseToAttorney: 'Neon has the checkbox value, but not associated text; we have no checkbox field, but the text',
            // # This is the text field
            // disclose_to_legislator:                             $this->inArray('1487', $releaseTo),
            discloseToLegislator: 'Neon has the checkbox value, but not associated text; we have no checkbox field, but the text',
            // # This is the text field
            // disclose_to_employer:                               $this->inArray('1486', $releaseTo),
            discloseToEmployer: 'Neon has the checkbox value, but not associated text; we have no checkbox field, but the text',
            // # This is the text field
            // disclose_to_governors_staff:                        $this->inArray('1488', $releaseTo),
            discloseToGovernorsStaff: 'Neon has the checkbox value, but not associated text; we have no checkbox field, but the text',
            // # Pre-filled
            // other_discloser:                                    $d['releaseToOther']['displayValue'] ?? '',
            // # Pre-filled
            // purpose_eligibility_determination:                  $this->inArray('585', $purposes),
            // # Pre-filled
            // purpose_employment:                                 $this->inArray('594', $purposes),
            purposeContinuityOfServicesCare: self::inArray('447', $purposes),
            purposeLegalConsultationRepresentation: self::inArray('1490', $purposes),
            purposeComplaintInvestigationResolution: self::inArray('1491', $purposes),
            purposeBackgroundInvestigation: self::inArray('1492', $purposes),
            purposeLegalProceedings: self::inArray('1493', $purposes),
            purposeTreatmentPlanning: self::inArray('1494', $purposes),
            purposeAtConsumersRequest: self::inArray('1495', $purposes),
            purposeToShareOrRefer: self::inArray('755', $purposes),
            // This is the checkbox for the 'other purpose' field which is pre-filled, but the box is not checked, hence 'Yes' here
            purposeOther: 'Yes', // $this->inArray('1496', $purposes),
            licensureInformation: self::inArray('161', $disclosed),
            disclosureMedical: self::inArray('1497', $disclosed),
            hotlineInvestigations: self::inArray('1499', $disclosed),
            homeStudies: self::inArray('1500', $disclosed),
            eligibilityDeterminations: self::inArray('1501', $disclosed),
            substanceAbuseTreatment: self::inArray('1502', $disclosed),
            clientEmploymentRecords: self::inArray('1503', $disclosed),
            acceptTextMessages: self::yesNo(self::nullableDisplayValue($d, 'acceptsTextMessage')),
            authorizeDiscloserFormOther: self::nullableValue($d, 'divisionOther'),
        );
    }

    /** @param NeonRecord $a */
    private static function transformAssessment(array $a): AssessmentDTO
    {
        $otherValue = self::nullableDisplayValue($a, 'other');

        return new AssessmentDTO(
            fullName: self::displayValue($a, 'persons_id'),
            dob: self::displayValue($a, 'dateOfBirth'),
            // # We should not collect this information
            // ssn:                                    null,
            eligibilityMissouriResident: self::yesNo(self::nullableDisplayValue($a, 'missouriResident')),
            eligibilityChildUnder18: self::yesNo(self::nullableDisplayValue($a, 'childUnder18')),
            financialEligibility: 'Off', // completed by state agency, not in Neon
            financialDriversLicence: self::yesNo(self::nullableDisplayValue($a, 'dL')),
            financialUtilityBill: self::yesNo(self::nullableDisplayValue($a, 'utilityBill')),
            financialWrittenEmployerStatement: self::yesNo(self::nullableDisplayValue($a, 'writtenEmployerStatement')),
            financialSsBenefitsStatement: self::yesNo(self::nullableDisplayValue($a, 'socialSecurityBenefitsStatement')),
            financialNoEmploymentIncome: self::yesNo(self::nullableDisplayValue($a, 'selfAttestationOfNoEmploymentOrIncome')),
            financialUnemploymentCompensation: self::yesNo(self::nullableDisplayValue($a, 'unemploymentCompensation')),
            financialOther: $otherValue ? 'Yes' : 'Off',
            financialOtherDescription: $otherValue,
            povertyMonthlyIncome: self::displayValue($a, 'hoseholdIncome'), // typo is in Neon field name
            povertyHouseholdMembers: self::value($a, 'numberOfFamilyMembersInHousehold'),
            povertyPercentageFpl: self::value($a, 'percentageOfFPL'),
        );
    }

    /** @param NeonRecord $s */
    private static function transformSurvey(array $s): SurveyDTO
    {
        $reasons = explode(',', self::value($s, 'reasons'));
        $howHeardAbout = explode(',', self::value($s, 'hearAboutUs'));
        $expectedGain = explode(',', self::value($s, 'expectToGain'));

        return new SurveyDTO(
            clientDob: self::displayValue($s, 'dateOfBirth'),
            deliveryMethod: '', // not in Neon — filled in by participant on paper
            why: match (true) {
                self::inArray('453', $reasons) => 'Responsible father',
                self::inArray('454', $reasons) => 'Referred',
                self::inArray('1506', $reasons) => 'Child support concerns',
                self::inArray('695', $reasons) => 'Attourney',  // matches PDF FieldStateOption spelling
                self::inArray('1507', $reasons) => 'Other',
                default => 'Off',
            },
            whyOther: self::value($s, 'reasonsOther'),
            how: match (true) {
                self::inArray('1510', $howHeardAbout) => 'Family support',
                self::inArray('1509', $howHeardAbout) => 'Past participant',
                self::inArray('1512', $howHeardAbout) => 'Marketing',
                self::inArray('1511', $howHeardAbout) => 'Prosecuting attorney',
                self::inArray('1513', $howHeardAbout) => 'The organization',
                self::inArray('1508', $howHeardAbout) => 'Word of mouth',
                self::inArray('1514', $howHeardAbout) => 'Other',
                default => 'Off',
            },
            howOther: self::value($s, 'hearAboutUsOther'),
            gain: match (true) {
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
            gainOther: self::value($s, 'expectToGainOther'),
        );
    }

    /** @param NeonRecord $sp */
    private static function transformServicePlan(array $sp): ServicePlanDTO
    {
        return new ServicePlanDTO(
            participantFullName: self::displayValue($sp, 'persons_id'),
            clientNumber: self::value($sp, 'clientNumber'),
            goal: '', // database appears to be missing this field per original comment
            serviceIdentified: self::value($sp, 'serviceIdentifiedByTheParticipants'),
            strategies1: self::displayValue($sp, 'goals_custodyVisitationObj'),
            personResponsible1: self::displayValue($sp, 'goals_custodyVisitationPersonRes'),
            timeline1: self::displayValue($sp, 'goals_custodyVisitationTimeline'),
            measureOfSuccess1: self::value($sp, 'goals_custodyVisitationMeasure'),
            strategies2: self::displayValue($sp, 'goals_educationEmploymentObj'),
            personResponsible2: self::displayValue($sp, 'goals_educationEmploymentPersonRes'),
            timeline2: self::displayValue($sp, 'goals_educationEmploymentTimeline'),
            measureOfSuccess2: self::value($sp, 'goals_educationEmploymentMeasure'),
            strategies3: self::displayValue($sp, 'goals_housingTransportationObj'),
            personResponsible3: self::displayValue($sp, 'goals_housingTransportationPersonRes'),
            timeline3: self::displayValue($sp, 'goals_housingTransportationTimeline'),
            measureOfSuccess3: self::value($sp, 'goals_housingTransportationMeasure'),
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private static function parseDate(?string $value): ?Carbon
    {
        return $value ? Date::createFromFormat('Y-m-d', $value) : null;
    }

    private static function parseDateString(?string $value): string
    {
        if (! $value) {
            return '';
        }

        $date = Date::createFromFormat('Y-m-d', $value);

        return $date instanceof Carbon ? $date->format('m/d/Y') : '';
    }

    /** @param NeonRecord $c */
    private static function buildAddress(array $c): string
    {
        return mb_trim(implode(' ', array_filter([
            self::value($c, 'address1'),
            self::value($c, 'address2'),
            self::value($c, 'city'),
            self::displayValue($c, 'state'),
            self::value($c, 'zip'),
        ])));
    }

    private static function yesNo(?string $value): string
    {
        return match ($value) {
            '1' => 'Yes',
            '0' => 'No',
            default => 'Off',
        };
    }

    private static function inList(string $list, string $id): string
    {
        return in_array($id, explode(',', $list)) ? 'Yes' : 'Off';
    }

    /** @param list<string> $arr */
    private static function inArray(string $id, array $arr): string
    {
        return in_array($id, $arr) ? 'Yes' : 'Off';
    }

    /**
     * @param  NeonEnvelope  $section
     * @return NeonRecord
     */
    private static function firstRecord(array $section): array
    {
        return $section['records'][0] ?? [];
    }

    /** @param NeonRecord $record */
    private static function value(array $record, string $field): string
    {
        $value = $record[$field]['value'] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param NeonRecord $record */
    private static function nullableValue(array $record, string $field): ?string
    {
        $value = $record[$field]['value'] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param NeonRecord $record */
    private static function displayValue(array $record, string $field): string
    {
        $value = $record[$field]['displayValue'] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param NeonRecord $record */
    private static function nullableDisplayValue(array $record, string $field): ?string
    {
        $value = $record[$field]['displayValue'] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
