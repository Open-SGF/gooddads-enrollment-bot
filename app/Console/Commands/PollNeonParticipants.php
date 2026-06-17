<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateParticipantPdfJob;
use App\Models\NeonHash;
use App\Services\NeonApiService;
use App\Transformers\NeonDTOTransformer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Override;

final class PollNeonParticipants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'neon:poll-participants {--date= : Date to process (defaults to today)}';

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

        try {
            $date = $this->option('date') ? $this->parseDate($this->option('date')) : Date::today('America/Chicago');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return;
        }

        $this->info(sprintf('🔍 Collecting participant records that have been added or updated today - %s....', $date));
        // $toReturn = $this->getParticipantIdsByDate($todaysDate);
        $fullRecords = $this->neonApi->getFullParticipantRecordsByDate($date->format('Y-m-d'));
        $count = count($fullRecords);
        $this->info(sprintf('📋 Found %d new or updated participant records.', $count));

        foreach ($fullRecords as $participantId => $fullRecord) {
            $participantId = (string) $participantId;

            // Create a hash of the full record
            $encodedRecord = json_encode($fullRecord);

            if ($encodedRecord === false) {
                $this->warning('⏭️ Participant '.$participantId.' could not be hashed. Skipping pdf regeneration.');

                continue;
            }

            $hash = hash('sha256', $encodedRecord);

            // Check if hash already exists
            if (! NeonHash::query()->where('id', $hash)->exists()) {
                $this->info('✅ Participant '.$participantId.' has updated data.');
                // Store the hash for the participant data for future comparison
                $this->info('🔄 Generating hash....');
                NeonHash::query()->create(['id' => $hash]);

                $this->info('🔄 Transforming participant data to serializable DTO');
                // Transform the participant data into serializable DTOs
                $participantData = NeonDTOTransformer::transformParticipantData($fullRecord);

                // Queue the pdf generation job
                $this->info('📬 Queing pdf regeneration');
                dispatch(new GenerateParticipantPdfJob($participantData));

            } else {
                $this->info('⏭️ Participant '.$participantId.' has no updated data. Skipping pdf regeneration.');
            }
        }

        $this->info('✅ Polling complete.');
    }

    private function parseDate(string $date): Carbon
    {
        $parsed = \Illuminate\Support\Facades\Date::createFromFormat('Y-m-d', $date);

        throw_if(! $parsed || $parsed->format('Y-m-d') !== $date, InvalidArgumentException::class, 'Invalid date format. Expected Y-m-d, e.g. 2026-06-16');

        return $parsed;
    }
}
