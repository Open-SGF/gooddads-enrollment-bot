<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class NeonApiService
{
    private ?string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.neon.base_url');
        $this->apiKey = config('services.neon.api_key');
    }

    private function ensureConfigured(): void
    {
        if (!$this->baseUrl) {
            throw new \RuntimeException('NEON_BASE_URL is not configured.');
        }

        if (!$this->apiKey) {
            throw new \RuntimeException('NEON_API_KEY is not configured.');
        }
    }

    public function getTodaysParticipantIds(): array
    {
        $todaysDate = Carbon::today('America/Chicago')->format('Y-m-d');
        $todaysDate = '2026-02-24';
        Log::info("🔍 Collecting participant records that have been added or updated today - {$todaysDate}....");
        // $toReturn = $this->getParticipantIdsByDate($todaysDate);
        $toReturn = $this->getFullParticipantRecordsByDate($todaysDate);
        $count = count($toReturn);
        Log::info("📋 Found {$count} new or updated participant records.");

        return $toReturn;
    }

    public function getParticipant(int $id): array
    {
        $fields = [
            // Core identity
            'persons_id',
            'firstName',
            'middleName',
            'lastName',
            'fullName',

            // Contact info
            'homeCellPhone',
            'otherNumber',
            'workPhone',
            'email',

            // Address
            'address1',
            'address2',
            'fullAddress',
            'city',
            'state',
            'zip',
            'regions_id',

            // Demographics
            'ethnicity',
            'maritalStatus',
            'tShirtSize',
            'birthday',

            // Employment
            'employer',

            // Case Worker
            'probationParoleCaseWorkerName',
            'probationParoleCaseWorkerPhone',

            // Application Info
            'applicationStatus',
            'applicationDate',

            // Child Support / Financial
            'monthlyChildSupportPayment',
            'monthlyIncome',
            'householdSize',
            'povertyPercentage',

            // Other dates
            'enteredDate',
            'updatedDate',
        ];

        $url = "{$this->baseUrl}/data/persons/{$id}";

        $response = Http::get($url, [
            'fields' => json_encode($fields),
            'key' => $this->apiKey,
        ]);

        $response->throw(); // will raise exception if not 200

        return $response->json();
    }

    public function fetchPersonContactInfo(string $personId, bool $useWhereClause): array
    {
        return $this->fetch("persons/{$personId}", [
            'firstName',
            'lastName',
            'regions_id',
            'enteredDate',
            'address1',
            'address2',
            'city',
            'state',
            'zip',
            'employer',
            'tShirtSize',
            'homeCellPhone',
            'workPhone',
            'otherNumber',
            'email',
            'probationParoleCaseWorkerName',
            'probationParoleCaseWorkerPhone',
            'contactWithChildren',
            'contactType',
            'monthlyChildSupportPayment',
            'maritalStatus',
            'ethnicity',
        ], $personId, $useWhereClause);
    }

    public function fetchPersonChildren(string $personId, bool $useWhereClause): array
    {
        return $this->fetch('persons_applications_children', [
            'firstName',
            'lastName',
            // Age to be calculated from dateOfBirth
            'dateOfBirth',
        ], $personId, $useWhereClause);
    }

    public function fetchPersonDisclosure(string $personId, bool $useWhereClause): array
    {
        return $this->fetch('persons_applications', [
            'persons_id',
            'division',
            'divisionOther',
            'homeCellPhone',
            'dateOfBirth',
            'fullAddress',
            'city',
            'state',
            'email',
            'releaseTo',
            'releaseToOther',
            'releaseToOtherAddress',
            'purposeOfDisclosure',
            'programName',
            'purposeOfDisclosureOther',
            'informationToBeDisclosed',
            'informationToBeDisclosedOther',
            'acceptsTextMessage',
        ], $personId,
            $useWhereClause);
    }

    public function fetchPersonAssessment(string $personId, bool $useWhereClause): array
    {
        return $this->fetch('persons_assessment_worksheet', [
            'persons_id',
            'fullName',
            'dateOfBirth',
            'missouriResident',
            'childUnder18',
            'financiallyEligible',
            'dL',
            'utilityBill',
            'payStub',
            'writtenEmployerStatement',
            'socialSecurityBenefitsStatement',
            'selfAttestationOfNoEmploymentOrIncome',
            'unemploymentCompensation',
            'other',
            'hoseholdIncome',
            'numberOfFamilyMembersInHousehold',
            'percentageOfFPL',
        ], $personId, $useWhereClause);
    }

    public function fetchPersonSurvey(string $personId, bool $useWhereClause): array
    {
        return $this->fetch('persons_introductory_survey', [
            'persons_id',
            'dateOfBirth',
            'programName',
            'reasons',
            'reasonsOther',
            'hearAboutUs',
            'hearAboutUsOther',
            'expectToGain',
            'expectToGainOther',
        ], $personId, $useWhereClause);
    }

    public function fetchPersonServicePlan(string $personId, bool $useWhereClause): array
    {
        return $this->fetch('persons_service_plan', [
            'persons_id',
            'programName',
            /**
             * This field is broken at Neon, it changes at every request breaking our hashing
             */
            // "clientNumber",
            'reviewDates',
            'serviceAreas',
            'serviceIdentifiedByTheParticipants',
            'goals_parentingSkills',
            'goals_parentingSkillsObj',
            'goals_parentingSkillsPersonRes',
            'goals_parentingSkillsTimeline',
            'goals_parentingSkillsMeasure',
            'goals_managingStress',
            'goals_managingStressObj',
            'goals_managingStressPersonRes',
            'goals_managingStressTimeline',
            'goals_managingStressMeasure',
            'goals_custodyVisitation',
            'goals_custodyVisitationObj',
            'goals_custodyVisitationPersonRes',
            'goals_custodyVisitationTimeline',
            'goals_custodyVisitationMeasure',
            'goals_educationEmployment',
            'goals_educationEmploymentObj',
            'goals_educationEmploymentPersonRes',
            'goals_educationEmploymentTimeline',
            'goals_educationEmploymentMeasure',
            'goals_housingTransportation',
            'goals_housingTransportationObj',
            'goals_housingTransportationPersonRes',
            'goals_housingTransportationTimeline',
            'goals_housingTransportationMeasure',
            'goals_childSupportAction',
            'goals_childSupportActionObj',
            'goals_childSupportActionPersonRes',
            'goals_childSupportActionTimeline',
            'goals_childSupportActionMeasure',
            'goals_childSupportAwareness',
            'goals_childSupportAwarenessObj',
            'goals_childSupportAwarenessPersonRes',
            'goals_childSupportAwarenessTimeline',
            'goals_childSupportAwarenessMeasure',
            'goals_effectiveCoParenting',
            'goals_effectiveCoParentingObj',
            'goals_effectiveCoParentingPersonRes',
            'goals_effectiveCoParentingTimeline',
            'goals_effectiveCoParentingMeasure',
            'goals_fatherToFatherMentoring',
            'goals_fatherToFatherMentoringObj',
            'goals_fatherToFatherMentoringPersonRes',
            'goals_fatherToFatherMentoringTimeline',
            'goals_fatherToFatherMentoringMeasure',
        ], $personId, $useWhereClause);
    }

    public function buildFullParticipantRecord(string $personId): array
    {
        return [
            'contactInfo' => $this->fetchPersonContactInfo($personId, false),
            'children' => $this->fetchPersonChildren($personId, true),
            'disclosure' => $this->fetchPersonDisclosure($personId, true),
            'assessment' => $this->fetchPersonAssessment($personId, true),
            'survey' => $this->fetchPersonSurvey($personId, true),
            'servicePlan' => $this->fetchPersonServicePlan($personId, true),
        ];
    }

    private function fetch(string $endpoint, array $fields = [], ?string $personId = null, bool $useWhereClause = true): array
    {
        $this->ensureConfigured();

        $url = "{$this->baseUrl}/data/{$endpoint}";

        $params = [
            'key' => $this->apiKey,
        ];

        if (! empty($fields)) {
            $params['fields'] = json_encode($fields);
        }

        // Add WHERE clause only if requested
        if ($personId !== null && $useWhereClause) {
            $params['where'] = json_encode([
                'whereType' => 'AND',
                'clauses' => [
                    [
                        'fieldName' => 'persons_id',
                        'operator' => '=',
                        'operand' => $personId,
                        'type' => 'id',
                    ],
                ],
            ]);
        }

        $response = Http::get($url, $params);
        $response->throw();

        $responseJson = $response->json() ?? [];

        if (isset($responseJson['status']) && $responseJson['status'] === 'error') {
            throw new Exception(
                $responseJson['errorMessage'] ?? 'Unknown error',
                $responseJson['errorCode'] ?? 0
            );
        }

        return $responseJson;
    }

    public function getFullParticipantRecordsByDate(string $filterDate): array
    {
        $this->ensureConfigured();

        $tableFieldMap = [
            'persons' => [
                'key' => 'contactInfo',
                'multi' => false,
                'fields' => [
                    'persons_id',
                    'firstName',
                    'lastName',
                    'regions_id',
                    'enteredDate',
                    'address1',
                    'address2',
                    'city',
                    'state',
                    'zip',
                    'employer',
                    'tShirtSize',
                    'homeCellPhone',
                    'workPhone',
                    'otherNumber',
                    'email',
                    'probationParoleCaseWorkerName',
                    'probationParoleCaseWorkerPhone',
                    'contactWithChildren',
                    'contactType',
                    'monthlyChildSupportPayment',
                    'maritalStatus',
                    'ethnicity',
                ],
            ],
            'persons_applications_children' => [
                'key' => 'children',
                'multi' => true,
                'fields' => [
                    'persons_id',
                    'firstName',
                    'lastName',
                    'dateOfBirth',
                ],
            ],
            'persons_applications' => [
                'key' => 'disclosure',
                'multi' => false,
                'fields' => [
                    'persons_id',
                    'division',
                    'divisionOther',
                    'homeCellPhone',
                    'dateOfBirth',
                    'fullAddress',
                    'city',
                    'state',
                    'email',
                    'releaseTo',
                    'releaseToOther',
                    'releaseToOtherAddress',
                    'purposeOfDisclosure',
                    'programName',
                    'purposeOfDisclosureOther',
                    'informationToBeDisclosed',
                    'informationToBeDisclosedOther',
                    'acceptsTextMessage',
                ],
            ],
            'persons_assessment_worksheet' => [
                'key' => 'assessment',
                'multi' => false,
                'fields' => [
                    'persons_id',
                    'fullName',
                    'dateOfBirth',
                    'missouriResident',
                    'childUnder18',
                    'financiallyEligible',
                    'dL',
                    'utilityBill',
                    'payStub',
                    'writtenEmployerStatement',
                    'socialSecurityBenefitsStatement',
                    'selfAttestationOfNoEmploymentOrIncome',
                    'unemploymentCompensation',
                    'other',
                    'hoseholdIncome',
                    'numberOfFamilyMembersInHousehold',
                    'percentageOfFPL',
                ],
            ],
            'persons_introductory_survey' => [
                'key' => 'survey',
                'multi' => false,
                'fields' => [
                    'persons_id',
                    'dateOfBirth',
                    'programName',
                    'reasons',
                    'reasonsOther',
                    'hearAboutUs',
                    'hearAboutUsOther',
                    'expectToGain',
                    'expectToGainOther',
                ],
            ],
            'persons_service_plan' => [
                'key' => 'servicePlan',
                'multi' => false,
                'fields' => [
                    'persons_id',
                    'programName',
                    'reviewDates',
                    'serviceAreas',
                    'serviceIdentifiedByTheParticipants',
                    'goals_parentingSkills',
                    'goals_parentingSkillsObj',
                    'goals_parentingSkillsPersonRes',
                    'goals_parentingSkillsTimeline',
                    'goals_parentingSkillsMeasure',
                    'goals_managingStress',
                    'goals_managingStressObj',
                    'goals_managingStressPersonRes',
                    'goals_managingStressTimeline',
                    'goals_managingStressMeasure',
                    'goals_custodyVisitation',
                    'goals_custodyVisitationObj',
                    'goals_custodyVisitationPersonRes',
                    'goals_custodyVisitationTimeline',
                    'goals_custodyVisitationMeasure',
                    'goals_educationEmployment',
                    'goals_educationEmploymentObj',
                    'goals_educationEmploymentPersonRes',
                    'goals_educationEmploymentTimeline',
                    'goals_educationEmploymentMeasure',
                    'goals_housingTransportation',
                    'goals_housingTransportationObj',
                    'goals_housingTransportationPersonRes',
                    'goals_housingTransportationTimeline',
                    'goals_housingTransportationMeasure',
                    'goals_childSupportAction',
                    'goals_childSupportActionObj',
                    'goals_childSupportActionPersonRes',
                    'goals_childSupportActionTimeline',
                    'goals_childSupportActionMeasure',
                    'goals_childSupportAwareness',
                    'goals_childSupportAwarenessObj',
                    'goals_childSupportAwarenessPersonRes',
                    'goals_childSupportAwarenessTimeline',
                    'goals_childSupportAwarenessMeasure',
                    'goals_effectiveCoParenting',
                    'goals_effectiveCoParentingObj',
                    'goals_effectiveCoParentingPersonRes',
                    'goals_effectiveCoParentingTimeline',
                    'goals_effectiveCoParentingMeasure',
                    'goals_fatherToFatherMentoring',
                    'goals_fatherToFatherMentoringObj',
                    'goals_fatherToFatherMentoringPersonRes',
                    'goals_fatherToFatherMentoringTimeline',
                    'goals_fatherToFatherMentoringMeasure',
                ],
            ],
        ];

        $whereClause = json_encode([
            'whereType' => 'OR',
            'clauses' => [
                [
                    'fieldName' => 'enteredDate',
                    'operator' => '>=',
                    'operand' => $filterDate,
                ],
                [
                    'fieldName' => 'updatedDate',
                    'operator' => '>=',
                    'operand' => $filterDate,
                ],
            ],
        ]);

        $baseUrl = "{$this->baseUrl}/data";
        $pageSize = config('services.neon.page_size', 200);

        // Fetch all records from each table, grouped by persons_id
        $tableRecords = [];

        foreach ($tableFieldMap as $table => $tableConfig) {
            $page = 1;
            $allRecords = [];

            do {
                $params = [
                    'key' => $this->apiKey,
                    'fields' => json_encode($tableConfig['fields']),
                    'where' => $whereClause,
                    'page' => $page,
                    'pageSize' => $pageSize,
                ];

                $url = "{$baseUrl}/{$table}";

                $response = Http::get($url, $params);
                $response->throw();
                $data = $response->json() ?? [];

                if (isset($data['status']) && $data['status'] === 'error') {
                    throw new Exception(
                        $data['errorMessage'] ?? 'Unknown error',
                        $data['errorCode'] ?? 0
                    );
                }

                $records = $data['records'] ?? [];
                
                $allRecords = array_merge($allRecords, $records);
                $page++;
            } while (! empty($records) && count($allRecords) < ($data['totalResults'] ?? 0));

            // Group records by persons_id
            $grouped = [];
            foreach ($allRecords as $record) {
                $personId = isset($record['persons_id']['value'])
                    ? (string) $record['persons_id']['value']
                    : null;
                if ($personId === null) {
                    continue;
                }

                if ($tableConfig['multi']) {
                    $grouped[$personId][] = $record;
                } else {
                    $grouped[$personId] = $record;
                }
            }

            $tableRecords[$table] = [
                'key' => $tableConfig['key'],
                'multi' => $tableConfig['multi'],
                'data' => $grouped,
            ];
        }

        // Build per-table participant ID lists (table names are retained as keys for debugging).
        $personIdSets = [];
        foreach ($tableRecords as $table => $tableData) {
            $personIdSets[$table] = array_map('strval', array_keys($tableData['data']));
        }

        // Build all/complete/incomplete IDs without variadic unpacking to avoid named-arg issues.
        $allPersonIdSet = [];
        $personIdPresenceCount = [];
        $tableCount = count($personIdSets);

        foreach ($personIdSets as $ids) {
            foreach (array_unique($ids) as $personId) {
                $allPersonIdSet[$personId] = true;
                $personIdPresenceCount[$personId] = ($personIdPresenceCount[$personId] ?? 0) + 1;
            }
        }

        $allPersonIds = array_keys($allPersonIdSet);
        $completePersonIds = [];

        foreach ($personIdPresenceCount as $personId => $presenceCount) {
            if ($presenceCount === $tableCount) {
                $completePersonIds[] = $personId;
            }
        }

        $incompletePersonIds = array_values(array_diff($allPersonIds, $completePersonIds));

        // Fallback fetchers for tables missing from a person's bulk data
        $fetchFallback = [
            'persons'                        => fn (string $id) => $this->fetchPersonContactInfo($id, false),
            'persons_applications_children'  => fn (string $id) => $this->fetchPersonChildren($id, true),
            'persons_applications'           => fn (string $id) => $this->fetchPersonDisclosure($id, true),
            'persons_assessment_worksheet'   => fn (string $id) => $this->fetchPersonAssessment($id, true),
            'persons_introductory_survey'    => fn (string $id) => $this->fetchPersonSurvey($id, true),
            'persons_service_plan'           => fn (string $id) => $this->fetchPersonServicePlan($id, true),
        ];

        // Build the person_id -> full record map
        $fullRecords = [];

        // Wraps a bulk-fetched value into the same {records:[...]} envelope that fetch() returns.
        $wrapBulk = function (mixed $value, bool $multi): array {
            return ['records' => $multi ? $value : [$value]];
        };

        // Already-complete persons: wrap bulk data to match fetch() response shape
        foreach ($completePersonIds as $personId) {
            $personId = (string) $personId;
            $record = [];
            foreach ($tableRecords as $table => $tableData) {
                $record[$tableData['key']] = $wrapBulk($tableData['data'][$personId], $tableData['multi']);
            }
            $fullRecords[$personId] = $record;
        }

        // Incomplete persons: bulk data gets wrapped; fallback already returns fetch() shape
        foreach ($incompletePersonIds as $personId) {
            $personId = (string) $personId;
            $record = [];
            foreach ($tableRecords as $table => $tableData) {
                $record[$tableData['key']] = isset($tableData['data'][$personId])
                    ? $wrapBulk($tableData['data'][$personId], $tableData['multi'])
                    : $fetchFallback[$table]((string) $personId);
            }
            $fullRecords[$personId] = $record;
        }

        return $fullRecords;
    }

    private function getParticipantIdsByDate(string $filterDate): array
    {
        $tables = ['persons', 'persons_applications_children', 'persons_applications', 'persons_assessment_worksheet', 'persons_introductory_survey', 'persons_service_plan'];
        $fields = ['persons_id', 'entered_date', 'updated_date'];
        $baseUrl = "{$this->baseUrl}/data";

        $params = [
            'key' => $this->apiKey,
        ];

        $params['fields'] = json_encode($fields);

        $params['where'] = json_encode([
            'whereType' => 'OR',
            'clauses' => [
                [
                    'fieldName' => 'enteredDate',
                    'operator' => '>=',
                    'operand' => $filterDate,
                ],
                [
                    'fieldName' => 'updatedDate',
                    'operator' => '>=',
                    'operand' => $filterDate,
                ],
            ],
        ]);

        $participantIds = [];

        foreach ($tables as $table) {
            $url = "{$baseUrl}/$table";
            $response = Http::get($url, $params);
            $data = $response->json() ?? [];
            $records = $data['records'] ?? [];

            if (! empty($records)) {
                $personsIds = array_column($records, 'persons_id');
                $newIds = array_column($personsIds, 'value');
                $participantIds = array_unique(array_merge($participantIds, $newIds));
            }

        }

        return $participantIds;
    }
}
