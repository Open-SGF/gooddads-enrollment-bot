<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * @phpstan-type NeonScalar bool|float|int|string|null
 * @phpstan-type NeonField array{value?: NeonScalar, displayValue?: NeonScalar}
 * @phpstan-type NeonRecord array<string, NeonField>
 * @phpstan-type NeonEnvelope array{records: list<NeonRecord>, totalResults?: int, status?: string, errorMessage?: string, errorCode?: int}
 * @phpstan-type NeonParticipantPayload array{
 *   contactInfo: NeonEnvelope,
 *   children: NeonEnvelope,
 *   disclosure: NeonEnvelope,
 *   assessment: NeonEnvelope,
 *   survey: NeonEnvelope,
 *   servicePlan: NeonEnvelope
 * }
 */
final readonly class NeonApiService
{
    private ?string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $baseUrl = config('services.neon.base_url');
        $apiKey = config('services.neon.api_key');

        $this->baseUrl = is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : null;
        $this->apiKey = is_string($apiKey) && $apiKey !== '' ? $apiKey : null;
    }

    /** @return NeonRecord */
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

        $url = sprintf('%s/data/persons/%d', $this->baseUrl, $id);

        $response = Http::get($url, [
            'fields' => json_encode($fields),
            'key' => $this->apiKey,
        ]);

        $response->throw(); // will raise exception if not 200

        return $this->normalizeRecord($response->json());
    }

    /** @return NeonParticipantPayload */
    public function buildFullParticipantRecord(string $personId): array
    {
        $this->ensureConfigured();

        $envelopes = $this->getMany($this->personSectionRequests($personId));

        return [
            'contactInfo' => $envelopes['contactInfo'],
            'children' => $envelopes['children'],
            'disclosure' => $envelopes['disclosure'],
            'assessment' => $envelopes['assessment'],
            'survey' => $envelopes['survey'],
            'servicePlan' => $envelopes['servicePlan'],
        ];
    }

    /** @return array<string, array<string, NeonEnvelope>> */
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

        throw_unless(is_string($whereClause), RuntimeException::class, 'Failed to encode Neon where clause.');

        $pageSizeConfig = config('services.neon.page_size', 200);
        $pageSize = is_int($pageSizeConfig) ? $pageSizeConfig : 200;

        $firstPageRequests = [];
        foreach ($tableFieldMap as $table => $tableConfig) {
            $firstPageRequests[$table] = $this->dataRequest(
                $table,
                $tableConfig['fields'],
                $whereClause,
                1,
                $pageSize,
            );
        }

        $firstPages = $this->getMany($firstPageRequests);
        $recordsByTable = [];
        $nextPageRequests = [];

        foreach ($tableFieldMap as $table => $tableConfig) {
            $envelope = $firstPages[$table];
            $records = $envelope['records'];
            $recordsByTable[$table] = $records;
            $totalResults = $envelope['totalResults'] ?? 0;

            if ($records === [] || count($records) >= $totalResults) {
                continue;
            }

            $lastPage = (int) ceil($totalResults / $pageSize);

            for ($page = 2; $page <= $lastPage; $page++) {
                $nextPageRequests[$table.':'.$page] = $this->dataRequest(
                    $table,
                    $tableConfig['fields'],
                    $whereClause,
                    $page,
                    $pageSize,
                );
            }
        }

        foreach ($this->getMany($nextPageRequests) as $key => $envelope) {
            $table = explode(':', $key, 2)[0];
            $recordsByTable[$table] = array_merge($recordsByTable[$table], $envelope['records']);
        }

        $tableRecords = [];
        foreach ($tableFieldMap as $table => $tableConfig) {
            $grouped = [];
            foreach ($recordsByTable[$table] as $record) {
                $personId = $this->recordPersonId($record);
                if ($personId === null) {
                    continue;
                }

                $grouped[$personId] ??= [];
                $grouped[$personId][] = $record;
            }

            $tableRecords[$table] = [
                'key' => $tableConfig['key'],
                'data' => $grouped,
            ];
        }

        // Build per-table participant ID lists (table names are retained as keys for debugging).
        $personIdSets = [];
        foreach ($tableRecords as $table => $tableData) {
            $personIdSets[$table] = array_map(strval(...), array_keys($tableData['data']));
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

        $fallbackRequests = [];
        foreach ($incompletePersonIds as $personId) {
            $personId = (string) $personId;
            $sections = $this->personSectionRequests($personId);

            foreach ($tableRecords as $table => $tableData) {
                if (! isset($tableData['data'][$personId])) {
                    $fallbackRequests[$personId.'|'.$table] = $sections[$tableData['key']];
                }
            }
        }

        $fallbackEnvelopes = $this->getMany($fallbackRequests);

        $fullRecords = [];

        foreach ($completePersonIds as $personId) {
            $personId = (string) $personId;
            $record = [];
            foreach ($tableRecords as $tableData) {
                /** @var list<NeonRecord> $recordsForPerson */
                $recordsForPerson = $tableData['data'][$personId];
                $record[$tableData['key']] = ['records' => $recordsForPerson];
            }

            $fullRecords[$personId] = $record;
        }

        foreach ($incompletePersonIds as $personId) {
            $personId = (string) $personId;
            $record = [];
            foreach ($tableRecords as $table => $tableData) {
                $record[$tableData['key']] = isset($tableData['data'][$personId])
                    ? ['records' => $tableData['data'][$personId]]
                    : $fallbackEnvelopes[$personId.'|'.$table];
            }

            $fullRecords[$personId] = $record;
        }

        return $fullRecords;
    }

    private function ensureConfigured(): void
    {
        throw_unless($this->baseUrl, RuntimeException::class, 'NEON_BASE_URL is not configured.');

        throw_unless($this->apiKey, RuntimeException::class, 'NEON_API_KEY is not configured.');
    }

    /**
     * @return array<string, array{url: string, query: array<string, mixed>}>
     */
    private function personSectionRequests(string $personId): array
    {
        $where = $this->personWhere($personId);

        return [
            'contactInfo' => $this->dataRequest('persons/'.$personId, [
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
            ]),
            'children' => $this->dataRequest('persons_applications_children', [
                'firstName',
                'lastName',
                'dateOfBirth',
            ], $where),
            'disclosure' => $this->dataRequest('persons_applications', [
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
            ], $where),
            'assessment' => $this->dataRequest('persons_assessment_worksheet', [
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
            ], $where),
            'survey' => $this->dataRequest('persons_introductory_survey', [
                'persons_id',
                'dateOfBirth',
                'programName',
                'reasons',
                'reasonsOther',
                'hearAboutUs',
                'hearAboutUsOther',
                'expectToGain',
                'expectToGainOther',
            ], $where),
            'servicePlan' => $this->dataRequest('persons_service_plan', [
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
            ], $where),
        ];
    }

    private function personWhere(string $personId): string
    {
        $where = json_encode([
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

        throw_unless(is_string($where), RuntimeException::class, 'Failed to encode Neon where clause.');

        return $where;
    }

    /**
     * @param  list<string>  $fields
     * @return array{url: string, query: array<string, mixed>}
     */
    private function dataRequest(string $endpoint, array $fields, ?string $where = null, ?int $page = null, ?int $pageSize = null): array
    {
        $query = ['key' => $this->apiKey];

        if ($fields !== []) {
            $query['fields'] = json_encode($fields);
        }

        if ($where !== null) {
            $query['where'] = $where;
        }

        if ($page !== null && $pageSize !== null) {
            $query['page'] = $page;
            $query['pageSize'] = $pageSize;
        }

        return [
            'url' => sprintf('%s/data/%s', $this->baseUrl, $endpoint),
            'query' => $query,
        ];
    }

    /**
     * @param  array<string, array{url: string, query: array<string, mixed>}>  $requests
     * @return array<string, NeonEnvelope>
     */
    private function getMany(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        /** @var array<string, Response|Throwable> $responses */
        $responses = Http::pool(function (Pool $pool) use ($requests): void {
            foreach ($requests as $key => $request) {
                $pool->as($key)->get($request['url'], $request['query']);
            }
        });

        $envelopes = [];

        foreach ($requests as $key => $_) {
            $response = $responses[$key] ?? null;

            throw_unless($response instanceof Response, $response instanceof Throwable
                ? $response
                : new RuntimeException("Missing Neon response for [{$key}]."));

            $response->throw();
            $envelopes[$key] = $this->parseEnvelope($response->json());
        }

        return $envelopes;
    }

    /** @return NeonEnvelope */
    private function parseEnvelope(mixed $responseJson): array
    {
        $response = is_array($responseJson) ? $responseJson : [];

        if (($response['status'] ?? null) === 'error') {
            $message = is_string($response['errorMessage'] ?? null) ? $response['errorMessage'] : 'Unknown error';
            $code = is_int($response['errorCode'] ?? null) ? $response['errorCode'] : 0;

            throw new Exception($message, $code);
        }

        $records = [];
        $responseRecords = $response['records'] ?? [];

        if (! is_array($responseRecords)) {
            $responseRecords = [];
        }

        foreach ($responseRecords as $record) {
            $records[] = $this->normalizeRecord($record);
        }

        $envelope = ['records' => $records];

        if (is_int($response['totalResults'] ?? null)) {
            $envelope['totalResults'] = $response['totalResults'];
        }

        if (is_string($response['status'] ?? null)) {
            $envelope['status'] = $response['status'];
        }

        if (is_string($response['errorMessage'] ?? null)) {
            $envelope['errorMessage'] = $response['errorMessage'];
        }

        if (is_int($response['errorCode'] ?? null)) {
            $envelope['errorCode'] = $response['errorCode'];
        }

        return $envelope;
    }

    /** @return NeonRecord */
    private function normalizeRecord(mixed $record): array
    {
        if (! is_array($record)) {
            return [];
        }

        $normalized = [];

        foreach ($record as $key => $field) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_array($field)) {
                continue;
            }

            $normalized[$key] = $this->normalizeField($field);
        }

        return $normalized;
    }

    /** @param NeonRecord $record */
    private function recordPersonId(array $record): ?string
    {
        $personField = $record['persons_id'] ?? [];
        $value = $personField['value'] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<mixed, mixed>  $field
     * @return NeonField
     */
    private function normalizeField(array $field): array
    {
        $value = $field['value'] ?? null;
        $displayValue = $field['displayValue'] ?? null;

        $hasScalarValue = is_bool($value) || is_float($value) || is_int($value) || is_string($value) || $value === null;
        $hasScalarDisplayValue = is_bool($displayValue) || is_float($displayValue) || is_int($displayValue) || is_string($displayValue) || $displayValue === null;

        if ($hasScalarValue && $hasScalarDisplayValue) {
            return ['value' => $value, 'displayValue' => $displayValue];
        }

        if ($hasScalarValue) {
            return ['value' => $value];
        }

        if ($hasScalarDisplayValue) {
            return ['displayValue' => $displayValue];
        }

        return [];
    }
}
