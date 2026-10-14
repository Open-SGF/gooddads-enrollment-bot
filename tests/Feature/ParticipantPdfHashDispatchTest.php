<?php

declare(strict_types=1);

use App\Jobs\GenerateParticipantPdfJob;
use App\Models\NeonHash;
use App\Services\NeonApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.neon.base_url', 'https://neon.example.test');
    config()->set('services.neon.api_key', 'test-key');
    Http::preventStrayRequests();
    Queue::fake();

    $this->email = 'first@example.org';
    Http::fake([
        'https://neon.example.test/*' => fn () => Http::response(['records' => [[
            'persons_id' => ['value' => '123', 'displayValue' => 'Test Participant'],
            'firstName' => ['value' => 'Test', 'displayValue' => 'Test'],
            'lastName' => ['value' => 'Participant', 'displayValue' => 'Participant'],
            'email' => ['value' => $this->email, 'displayValue' => null],
        ]]]),
    ]);
});

it('keeps polling hash comparisons and carries the exact Neon hash to generation', function (): void {
    $record = resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-10-13')['123'];
    $hash = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));

    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, fn (GenerateParticipantPdfJob $job): bool => $job->neonDataHash === $hash);

    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, 1);

    $this->email = 'changed@example.org';
    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, 2);
    Queue::assertPushed(GenerateParticipantPdfJob::class, fn (GenerateParticipantPdfJob $job): bool => $job->neonDataHash !== $hash);

    // Preserve the existing behavior of skipping any hash seen before, including reversions.
    $this->email = 'first@example.org';
    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, 2);
    expect(NeonHash::query()->count())->toBe(2);
});

it('carries the original hash on manual regeneration even when it is unchanged', function (): void {
    $record = resolve(NeonApiService::class)->buildFullParticipantRecord('123');
    $hash = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));

    $this->artisan('neon:fetch-by-id', ['id' => '123'])->assertSuccessful();
    $this->artisan('neon:fetch-by-id', ['id' => '123'])->assertSuccessful();

    Queue::assertPushed(GenerateParticipantPdfJob::class, 2);
    foreach (Queue::pushed(GenerateParticipantPdfJob::class) as $job) {
        expect($job->neonDataHash)->toBe($hash);
    }

    expect(NeonHash::query()->count())->toBe(1);
});
