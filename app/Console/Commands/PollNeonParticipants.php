<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateParticipantPdfJob;
use App\Models\NeonHash;
use App\Services\NeonApiService;
use App\Transformers\NeonDTOTransformer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Override;

final class PollNeonParticipants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'neon:poll-participants';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = "Polls Neon for today's participants and queues PDFs for new records";

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

        // Returns a map of personId => fullRecord (contactInfo, children, disclosure, assessment, survey, servicePlan)
        $fullRecords = $this->neonApi->getTodaysParticipantIds();

        foreach ($fullRecords as $participantId => $fullRecord) {
            $participantId = (string) $participantId;

            // Create a hash of the full record
            $encodedRecord = json_encode($fullRecord);

            if ($encodedRecord === false) {
                Log::warning('⏭️ Participant '.$participantId.' could not be hashed. Skipping pdf regeneration.');

                continue;
            }

            $hash = hash('sha256', $encodedRecord);

            // Check if hash already exists
            if (! NeonHash::query()->where('id', $hash)->exists()) {
                Log::info('✅ Participant '.$participantId.' has updated data.');
                // Store the hash for the participant data for future comparison
                Log::info('🔄 Generating hash....');
                NeonHash::query()->create(['id' => $hash]);

                Log::info('🔄 Transforming participant data to serializable DTO');
                // Transform the participant data into serializable DTOs
                $participantData = NeonDTOTransformer::transformParticipantData($fullRecord);

                // Queue the pdf generation job
                Log::info('📬 Queing pdf regeneration');
                dispatch(new GenerateParticipantPdfJob($participantData));

            } else {
                Log::info('⏭️ Participant '.$participantId.' has no updated data. Skipping pdf regeneration.');
            }
        }

        $this->info('✅ Polling complete.');
    }
}
