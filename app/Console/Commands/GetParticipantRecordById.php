<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateParticipantPdfJob;
use App\Models\NeonHash;
use App\Services\NeonApiService;
use App\Transformers\NeonDTOTransformer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Description('Polls Neon for a specified participantid and queues PDF generation')]
#[Signature('neon:fetch-by-id {id : Participant id to process}')]
final class GetParticipantRecordById extends Command
{
    public function __construct(/**
     * Inject NeonApiService.
     */
        private readonly NeonApiService $neonApi)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $id = $this->argument('id');

        if (! is_numeric($id)) {
            Log::warning('Invalid participant ID.', ['participant_id' => (string) $id]);
            $this->error('Invalid participant ID.');

            return;
        }

        Log::info('Neon participant fetch started.', ['participant_id' => (string) $id]);
        $this->info('Collecting participant records.');
        $record = $this->neonApi->buildFullParticipantRecord($id);

        // Extract all 'records' sub-arrays dynamically from the parent array
        $allExtractedRecords = array_column($record, 'records');

        // Filter out any empty arrays. If the result is empty, ALL records are empty.
        if (array_filter($allExtractedRecords) === []) {
            Log::warning('Neon participant not found.', ['participant_id' => (string) $id]);
            $this->error('No participant found.');

            return;
        }

        // Create a hash of the full record
        $encodedRecord = json_encode($record);

        if ($encodedRecord === false) {
            Log::warning('Participant record could not be hashed.', ['participant_id' => (string) $id]);
            $this->warn('Participant record could not be hashed. Skipping PDF regeneration.');

            return;
        }

        $hash = hash('sha256', $encodedRecord);

        // Check if hash already exists
        if (! NeonHash::query()->where('id', $hash)->exists()) {
            $this->info('Generating hash.');
            NeonHash::query()->create(['id' => $hash]);
        }

        $this->info('Transforming participant data to serializable DTO');
        // Transform the participant data into serializable DTOs
        $participantData = NeonDTOTransformer::transformParticipantData($record);

        // Queue the pdf generation job
        $this->info('Queuing PDF regeneration');
        dispatch(new GenerateParticipantPdfJob($participantData));
        Log::info('Participant PDF generation queued.', ['participant_id' => (string) $id]);
        $this->info('Participant processing queued.');
    }
}
