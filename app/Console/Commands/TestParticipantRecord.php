<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NeonApiService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Description('Test the buildParticipantRecord() method and output the JSON response')]
#[Signature('app:test-participant-record {id : The ID of the participant to test}')]
final class TestParticipantRecord extends Command
{
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
