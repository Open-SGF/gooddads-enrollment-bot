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
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

#[Description("Polls Neon for today's participants and queues PDFs for new records")]
#[Signature('neon:poll-participants {--date= : Date to process Y-m-d (defaults to today)}')]
final class PollNeonParticipants extends Command
{
    public function __construct(private readonly NeonApiService $neonApi)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        try {
            $date = $this->option('date') ? $this->parseDate($this->option('date')) : Date::today('America/Chicago');
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return;
        }

        $pollId = (string) Str::uuid();
        $filterDate = $date->format('Y-m-d');
        $started = hrtime(true);

        Log::info('Neon poll started.', ['poll_id' => $pollId, 'date' => $filterDate]);

        try {
            $span = Globals::tracerProvider()->getTracer('gooddads-enrollment-bot')
                ->spanBuilder('neon.fetch')->startSpan();
            $scope = $span->activate();
            $fetchStarted = hrtime(true);

            try {
                $fullRecords = $this->neonApi->getFullParticipantRecordsByDate($filterDate);
                $span->setAttribute('participants.count', count($fullRecords));
            } catch (Throwable $exception) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR);

                throw $exception;
            } finally {
                $scope->detach();
                $span->end();
            }

            $count = count($fullRecords);
            Log::info('Neon participants fetched.', [
                'poll_id' => $pollId,
                'date' => $filterDate,
                'participants' => $count,
                'duration_ms' => (int) ((hrtime(true) - $fetchStarted) / 1_000_000),
            ]);
            $this->info('Participant records fetched.');

            $queued = 0;
            $unchanged = 0;
            $skipped = 0;

            foreach ($fullRecords as $participantId => $fullRecord) {
                $encodedRecord = json_encode($fullRecord);

                if ($encodedRecord === false) {
                    $skipped++;
                    Log::warning('Participant record could not be hashed.', [
                        'poll_id' => $pollId,
                        'participant_id' => (string) $participantId,
                    ]);

                    continue;
                }

                $hash = hash('sha256', $encodedRecord);

                if (NeonHash::query()->where('id', $hash)->exists()) {
                    $unchanged++;

                    continue;
                }

                NeonHash::query()->create(['id' => $hash]);
                $participantData = NeonDTOTransformer::transformParticipantData($fullRecord);
                dispatch(new GenerateParticipantPdfJob($participantData, $pollId));
                $queued++;
            }

            $counts = ['queued' => $queued, 'unchanged' => $unchanged, 'skipped' => $skipped];
            Span::getCurrent()->addEvent('participants.classified', $counts);
            Log::info('Neon poll completed.', [
                'poll_id' => $pollId,
                'date' => $filterDate,
                'participants' => $count,
                ...$counts,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);
            $this->info('Polling complete.');
        } catch (Throwable $throwable) {
            Log::error('Neon poll failed.', [
                'poll_id' => $pollId,
                'date' => $filterDate,
                'exception' => $throwable::class,
                'status_code' => $throwable instanceof RequestException ? $throwable->response->status() : null,
            ]);

            throw $throwable;
        }
    }

    private function parseDate(string $date): Carbon
    {
        $parsed = Date::createFromFormat('Y-m-d', $date);

        throw_if(! $parsed || $parsed->format('Y-m-d') !== $date, InvalidArgumentException::class, 'Invalid date format. Expected Y-m-d, e.g. 2026-06-16');

        return $parsed;
    }
}
