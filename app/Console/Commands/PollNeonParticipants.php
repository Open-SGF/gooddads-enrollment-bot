<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateParticipantPdfJob;
use App\Models\NeonHash;
use App\Services\NeonApiService;
use App\Transformers\NeonDTOTransformer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class PollNeonParticipants extends Command
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

        foreach ($participantIds as $participantId) {
            // Get the full participant record
            $fullRecord = $this->neonApi->buildFullParticipantRecord($participantId);

            // Create a hash of the full record
            $hash = hash('sha256', json_encode($fullRecord));

            // Check if hash already exists
            if (! NeonHash::where('id', $hash)->exists()) {
                Log::info('✅ Participant '.$participantId.' has updated data.');
                // Store the hash for the participant data for future comparison
                Log::info('🔄 Generating hash....');
                NeonHash::create(['id' => $hash]);

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
