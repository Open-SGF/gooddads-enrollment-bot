<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Integrations\NeonApiService;
use App\Services\NeonDTOTransformer;
use App\Jobs\GenerateParticipantPdfJob; 
use App\Models\NeonHash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PollNeonParticipants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'neon:poll-participants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Polls Neon for today\'s participants and queues PDFs for new records';

    /**
     * Inject NeonApiService.
     */
    protected NeonApiService $neonApi;

    public function __construct(NeonApiService $neonApi)
    {
        parent::__construct();
        $this->neonApi = $neonApi;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        $participantIds = $this->neonApi->getTodaysParticipantIds();

        Log::info('Found '.count($participantIds).' participant records to check for updates.');
   
        foreach ($participantIds as $participantId) {
            // Get the full participant record
            $fullRecord = $this->neonApi->buildFullParticipantRecord($participantId);   


            // Create a hash of the full record
            $hash = hash('sha256', json_encode($fullRecord));

            // Check if hash already exists
            if (!NeonHash::where('id', $hash)->exists()) {
                Log::info("Participant ". $participantId . " has updated data. Queuing pdf regeneration.");
                // Store the hash for the participant data for future comparison
                NeonHash::create(['id' => $hash]);

                // Transform the participant data into serializable DTOs
                $serializableDTOs = NeonDTOTransformer::transformParticipantData($fullRecord);
                // Queue the pdf generation job
                dispatch(new GenerateParticipantPdfJob($serializableDTOs));
            } else {
                Log::info("Participant ". $participantId . " has no updated data. Skipping pdf regeneration.");
            }
        }

        $this->info('Polling complete.');
    }
}
