<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParticipantUpdateData;
use App\DTOs\PdfGenerationResultDTO;
use App\Services\DropboxUploadService;
use App\Services\ParticipantPdfVersionService;
use App\Services\PdfIntakeFormService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

final class GenerateParticipantPdfJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ParticipantUpdateData $updatedParticipantData,
        public readonly string $neonDataHash,
        public readonly ?string $pollId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->updatedParticipantData->id.':'.$this->neonDataHash;
    }

    public function handle(
        PdfIntakeFormService $pdfService,
        DropboxUploadService $dropboxService,
        ParticipantPdfVersionService $versionService,
    ): void {
        $context = [
            'poll_id' => $this->pollId,
            'participant_id' => $this->updatedParticipantData->id,
            'job_id' => $this->job?->uuid(),
            'attempt' => $this->job?->attempts(),
        ];
        $started = hrtime(true);
        $stage = 'participant.validate';

        try {
            $missingFields = $this->updatedParticipantData->getMissingFields();
            if ($missingFields !== []) {
                Span::getCurrent()->addEvent('delivery.blocked', ['reason' => 'missing_fields']);
                Log::warning('Participant delivery blocked by missing required fields.', [
                    ...$context,
                    'missing_fields' => $missingFields,
                ]);

                return;
            }

            $stage = 'pdf.generate';
            $span = Globals::tracerProvider()->getTracer('gooddads-enrollment-bot')
                ->spanBuilder('pdf.generate')->startSpan();
            $scope = $span->activate();

            try {
                $pdfGenerationResult = $versionService->generate(
                    $this->updatedParticipantData,
                    $pdfService,
                    $this->neonDataHash,
                    jobUuid: $this->job?->uuid(),
                );
            } catch (Throwable $exception) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR);

                throw $exception;
            } finally {
                $scope->detach();
                $span->end();
            }

            if (! $pdfGenerationResult instanceof PdfGenerationResultDTO) {
                Log::info('Participant PDF skipped because the latest version has the same Neon hash.', $context);

                return;
            }

            $version = $pdfGenerationResult->version ?? throw new LogicException('Generated PDF is missing its version.');
            $stage = 'dropbox.upload';
            $dropboxPath = 'participant-forms/'.$this->updatedParticipantData->id.'/'.$pdfGenerationResult->filename;
            $dropboxService->upload($pdfGenerationResult->contents, $dropboxPath, overwrite: true);

            $stage = 'email.queue';
            $queued = 0;
            $skipped = 0;
            // Keep all recipient inserts together on the application's database queue.
            DB::transaction(function () use ($version, $context, &$queued, &$skipped): void {
                foreach (config()->array('mail.intake_form_recipients') as $index => $recipient) {
                    if (! is_string($recipient) || validator(['email' => $recipient], ['email' => 'required|string|email'])->fails()) {
                        $skipped++;
                        Span::getCurrent()->addEvent('email.skipped', ['reason' => 'invalid_recipient']);
                        Log::warning('Skipping PDF email for invalid recipient.', [...$context, 'recipient_index' => $index]);

                        continue;
                    }

                    dispatch(new SendParticipantPdfEmailJob($this->updatedParticipantData, $version, $recipient, $this->pollId));
                    $queued++;
                }
            });

            Log::info('Participant PDF uploaded and email delivery queued.', [
                ...$context,
                'pdf_version' => $version->version,
                'email_queued' => $queued,
                'email_skipped' => $skipped,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            ]);
        } catch (Throwable $throwable) {
            Log::error('Participant processing failed.', [
                ...$context,
                'stage' => $stage,
                'exception' => $throwable::class,
                'error_code' => $throwable->getCode() ?: null,
            ]);

            throw $throwable;
        }
    }
}
