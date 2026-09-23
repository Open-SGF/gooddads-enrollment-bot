<?php

declare(strict_types=1);

use App\Services\NeonApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.neon.base_url', 'https://neon.test');
    config()->set('services.neon.api_key', 'test-key');
    config()->set('services.neon.page_size', 2);
    Http::preventStrayRequests();
});

it('fetches every Neon table for a date in one pool and skips extra person lookups', function (): void {
    Http::fake(function (Request $request): array {
        $person = neonRecord('10');

        return match (neonPath($request)) {
            '/data/persons',
            '/data/persons_applications_children',
            '/data/persons_applications',
            '/data/persons_assessment_worksheet',
            '/data/persons_introductory_survey',
            '/data/persons_service_plan' => neonEnvelope([$person], 1),
            default => throw new RuntimeException('Unexpected Neon path '.$request->url()),
        };
    });

    $records = resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-09-22');

    expect($records)->toHaveKey('10')
        ->and($records['10']['contactInfo']['records'][0]['persons_id']['value'])->toBe('10')
        ->and($records['10']['children']['records'])->toHaveCount(1)
        ->and($records['10']['servicePlan']['records'])->toHaveCount(1);

    Http::assertSentCount(6);
    Http::assertNotSent(fn (Request $request): bool => str_contains(neonPath($request), '/data/persons/'));
});

it('requests remaining Neon pages after the first page reports more results', function (): void {
    Http::fake(function (Request $request): array {
        $path = neonPath($request);
        $page = (int) $request['page'];

        if ($path === '/data/persons' && $page === 1) {
            return neonEnvelope([neonRecord('1'), neonRecord('2')], 3);
        }

        if ($path === '/data/persons' && $page === 2) {
            return neonEnvelope([neonRecord('3')], 3);
        }

        if (in_array($path, neonTablePaths(), true)) {
            return neonEnvelope([neonRecord('1'), neonRecord('2'), neonRecord('3')], 3);
        }

        throw new RuntimeException('Unexpected Neon path '.$request->url());
    });

    $records = resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-09-22');

    expect($records)->toHaveKeys(['1', '2', '3']);

    Http::assertSent(fn (Request $request): bool => neonPath($request) === '/data/persons' && (int) $request['page'] === 1);
    Http::assertSent(fn (Request $request): bool => neonPath($request) === '/data/persons' && (int) $request['page'] === 2);
    Http::assertSentCount(7);
});

it('falls back to per-person Neon lookups only for missing tables', function (): void {
    Http::fake(function (Request $request): array {
        $path = neonPath($request);

        if (isset($request['page'])) {
            if ($path === '/data/persons') {
                return neonEnvelope([neonRecord('10'), neonRecord('11')], 2);
            }

            if (in_array($path, neonTablePaths(), true)) {
                return neonEnvelope([neonRecord('10')], 1);
            }
        }

        if ($path === '/data/persons_applications_children') {
            return neonEnvelope([neonRecord('11', ['firstName' => ['value' => 'Pat']])]);
        }

        if (in_array($path, [
            '/data/persons_applications',
            '/data/persons_assessment_worksheet',
            '/data/persons_introductory_survey',
            '/data/persons_service_plan',
        ], true)) {
            return neonEnvelope([neonRecord('11')]);
        }

        throw new RuntimeException('Unexpected Neon path '.$request->url());
    });

    $records = resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-09-22');

    expect($records)->toHaveKeys(['10', '11'])
        ->and($records['11']['children']['records'][0]['firstName']['value'])->toBe('Pat');

    Http::assertSentCount(11);
    Http::assertNotSent(fn (Request $request): bool => neonPath($request) === '/data/persons/11');
    Http::assertSent(fn (Request $request): bool => neonPath($request) === '/data/persons_applications_children' && is_string($request['where'] ?? null));
});

it('loads a single participant record with concurrent section requests', function (): void {
    Http::fake(function (Request $request): array {
        $path = neonPath($request);

        if ($path === '/data/persons/42') {
            return neonEnvelope([neonRecord('42', ['firstName' => ['value' => 'Alex']])]);
        }

        if (in_array($path, [
            '/data/persons_applications_children',
            '/data/persons_applications',
            '/data/persons_assessment_worksheet',
            '/data/persons_introductory_survey',
            '/data/persons_service_plan',
        ], true)) {
            return neonEnvelope([neonRecord('42')]);
        }

        throw new RuntimeException('Unexpected Neon path '.$request->url());
    });

    $record = resolve(NeonApiService::class)->buildFullParticipantRecord('42');

    expect($record['contactInfo']['records'][0]['firstName']['value'])->toBe('Alex')
        ->and($record['survey']['records'])->toHaveCount(1);

    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request): bool => neonPath($request) === '/data/persons/42');
});

it('throws when a pooled Neon request fails', function (): void {
    Http::fake(function (Request $request) {
        if (neonPath($request) === '/data/persons') {
            return Http::response(['status' => 'error', 'errorMessage' => 'nope'], 500);
        }

        return neonEnvelope([]);
    });

    resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-09-22');
})->throws(RequestException::class);

it('throws when Neon returns an error envelope', function (): void {
    Http::fake(fn (): array => [
        'status' => 'error',
        'errorMessage' => 'bad query',
        'errorCode' => 7,
    ]);

    resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-09-22');
})->throws(Exception::class, 'bad query');

/**
 * @param  array<string, array{value?: mixed, displayValue?: mixed}>  $extra
 * @return array<string, array{value?: mixed, displayValue?: mixed}>
 */
function neonRecord(string $personId, array $extra = []): array
{
    return [
        'persons_id' => ['value' => $personId, 'displayValue' => $personId],
        ...$extra,
    ];
}

/**
 * @param  list<array<string, array{value?: mixed, displayValue?: mixed}>>  $records
 * @return array{records: list<array<string, array{value?: mixed, displayValue?: mixed}>>, totalResults: int, status: string}
 */
function neonEnvelope(array $records, ?int $totalResults = null): array
{
    return [
        'records' => $records,
        'totalResults' => $totalResults ?? count($records),
        'status' => 'success',
    ];
}

function neonPath(Request $request): string
{
    return (string) parse_url($request->url(), PHP_URL_PATH);
}

/**
 * @return list<string>
 */
function neonTablePaths(): array
{
    return [
        '/data/persons',
        '/data/persons_applications_children',
        '/data/persons_applications',
        '/data/persons_assessment_worksheet',
        '/data/persons_introductory_survey',
        '/data/persons_service_plan',
    ];
}
