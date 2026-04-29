<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NeonApiService;
use Illuminate\Console\Command;
use Override;
use Throwable;

final class TestParticipantRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'app:test-participant-record {id : The ID of the participant to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'Test the buildParticipantRecord() method and output the JSON response';

    public function __construct(/**
     * Inject NeonApiService.
     */
        private readonly NeonApiService $neon)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');

        $this->info('Testing buildParticipantRecord() for ID: '.$id);
        $this->newLine();

        try {
            $record = $this->neon->buildFullParticipantRecord($id);
            $encodedRecord = json_encode($record, JSON_PRETTY_PRINT);

            $this->line($encodedRecord === false ? 'Unable to encode record as JSON.' : $encodedRecord);

        } catch (Throwable $throwable) {
            $this->error('Error: '.$throwable->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
