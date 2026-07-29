<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\NeonApiService;
use App\Models\NeonHash;
use App\Transformers\NeonDTOTransformer;
use App\Jobs\GenerateParticipantPdfJob;

class GetParticipantRecordById extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'neon:fetch-by-id {id : Participant id to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = "Polls Neon for a specified participantid and queues PDF generation";

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
    public function handle()
    {
        $id = $this->argument('id');

        if (!is_numeric($id)) {
            $this->error("Invalid id - '$id' is not a number.");
            return;
        }

        $this->info(sprintf('🔍 Collecting records for participant id - %s....', $id));
        // $toReturn = $this->getParticipantIdsByDate($todaysDate);
        $record = $this->neonApi->buildFullParticipantRecord($id);
        
        $fullRecord = $this->neonApi->buildFullParticipantRecord($id);
        
        if (empty(array_filter($fullRecord))) {
            $this->error(sprintf("No participant found for id - %s", $id));
            return;
        }

        // Create a hash of the full record
            $encodedRecord = json_encode($fullRecord);

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
