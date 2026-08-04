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
            $this->error(sprintf("Invalid id - '%s' is not a number.", $id));

            return;
        }

        $this->info(sprintf('🔍 Collecting records for participant id - %s....', $id));
        $record = $this->neonApi->buildFullParticipantRecord($id);

        // Extract all 'records' sub-arrays dynamically from the parent array
        $allExtractedRecords = array_column($record, 'records');

        // Filter out any empty arrays. If the result is empty, ALL records are empty.
        if (array_filter($allExtractedRecords) === []) {
            $this->error(sprintf('No participant found for id - %s', $id));

            return;
        }

        // Create a hash of the full record
        $encodedRecord = json_encode($record);

        if ($encodedRecord === false) {
            $this->warn('⏭️ Participant '.$id.' could not be hashed. Skipping pdf regeneration.');

            return;
        }

        $hash = hash('sha256', $encodedRecord);

        // Check if hash already exists
        if (! NeonHash::query()->where('id', $hash)->exists()) {
            $this->info('🔄 Generating hash....');
            NeonHash::query()->create(['id' => $hash]);
        }

        $this->info('🔄 Transforming participant data to serializable DTO');
        // Transform the participant data into serializable DTOs
        $participantData = NeonDTOTransformer::transformParticipantData($record);

        // Queue the pdf generation job
        $this->info('📬 Queing pdf regeneration');
        dispatch(new GenerateParticipantPdfJob($participantData));
        $this->info('✅ Polling complete.');
    }
}
