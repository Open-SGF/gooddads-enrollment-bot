<?php

declare(strict_types=1);

use App\Jobs\GenerateParticipantPdfJob;
use App\Models\Participant;
use App\Models\ParticipantPdfVersion;
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

it('compares only the latest version and queues reversions without duplicating pending work', function (): void {
    $record = resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-10-13')['123'];
    $originalHash = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
    Participant::query()->create(['id' => '123']);
    $saveVersion = fn (int $number, string $hash) => ParticipantPdfVersion::query()->create([
        'participant_id' => '123',
        'version' => $number,
        'neon_data_hash' => $hash,
        'changed_fields' => [],
        'filename' => 'version_'.$number.'.pdf',
    ]);
    $saveVersion(1, $originalHash);

    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertNothingPushed();

    $this->email = 'changed@example.org';
    $changedRecord = resolve(NeonApiService::class)->getFullParticipantRecordsByDate('2026-10-13')['123'];
    $changedHash = hash('sha256', json_encode($changedRecord, JSON_THROW_ON_ERROR));
    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, 1);
    Queue::assertPushed(GenerateParticipantPdfJob::class, fn (GenerateParticipantPdfJob $job): bool => $job->neonDataHash === $changedHash);
    $saveVersion(2, $changedHash);

    $this->email = 'first@example.org';
    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, 2);
    Queue::assertPushed(GenerateParticipantPdfJob::class, fn (GenerateParticipantPdfJob $job): bool => $job->neonDataHash === $originalHash);
    $saveVersion(3, $originalHash);

    $this->artisan('neon:poll-participants', ['--date' => '2026-10-13'])->assertSuccessful();
    Queue::assertPushed(GenerateParticipantPdfJob::class, 2);
});

it('queues manual fetches with the original hash and the same deduplication as polling', function (): void {
    $record = resolve(NeonApiService::class)->buildFullParticipantRecord('123');
    $hash = hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));

    $this->artisan('neon:fetch-by-id', ['id' => '123'])->assertSuccessful();
    $this->artisan('neon:fetch-by-id', ['id' => '123'])->assertSuccessful();

    Queue::assertPushed(GenerateParticipantPdfJob::class, 1);
    Queue::assertPushed(GenerateParticipantPdfJob::class, fn (GenerateParticipantPdfJob $job): bool => $job->neonDataHash === $hash);
});
