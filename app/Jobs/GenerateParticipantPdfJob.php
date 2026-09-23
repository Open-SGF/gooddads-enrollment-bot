<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParticipantUpdateData;
use App\Mail\IntakeFormMailable;
use App\Services\DropboxUploadService;
use App\Services\PdfIntakeFormService;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

final class GenerateParticipantPdfJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ParticipantUpdateData $updatedParticipantData,
        public readonly ?string $pollId = null,
    ) {}

    public function handle(
        PdfIntakeFormService $pdfService,
        DropboxUploadService $dropboxService
    ): void {
        $context = [
            'poll_id' => $this->pollId,
            'participant_id' => $this->updatedParticipantData->id,
            'job_id' => $this->job?->uuid(),
            'attempt' => $this->job?->attempts(),
        ];
        $started = hrtime(true);
        $stage = 'pdf.generate';

        try {
            $span = Globals::tracerProvider()->getTracer('gooddads-enrollment-bot')
                ->spanBuilder('pdf.generate')->startSpan();
            $scope = $span->activate();

            try {
                $pdfGenerationResult = $pdfService->generate($this->updatedParticipantData);
            } catch (Throwable $exception) {
                $span->recordException($exception);
                $span->setStatus(StatusCode::STATUS_ERROR);

                throw $exception;
            } finally {
                $scope->detach();
                $span->end();
            }

            $stage = 'participant.validate';
            $missingFields = $this->updatedParticipantData->getMissingFields();
            if ($missingFields !== []) {
                Span::getCurrent()->addEvent('delivery.blocked', ['reason' => 'missing_fields']);
                Log::warning('Participant delivery blocked by missing required fields.', [
                    ...$context,
                    'missing_fields' => $missingFields,
                ]);

                return;
            }

            $stage = 'dropbox.upload';
            $dropbox = 'succeeded';
            $dropboxPath = 'participant-forms/'.$this->updatedParticipantData->id.'/'.$pdfGenerationResult->filename;
            try {
                $dropboxService->upload($pdfGenerationResult->contents, $dropboxPath);
            } catch (Throwable $exception) {
                $dropbox = 'failed';
                Span::getCurrent()->addEvent('dropbox.upload_failed', ['exception.type' => $exception::class]);
                Span::getCurrent()->setStatus(StatusCode::STATUS_ERROR);
                Log::warning('Dropbox upload failed; continuing to email.', [
                    ...$context,
                    'exception' => $exception::class,
                    'status_code' => $exception instanceof RequestException ? $exception->getResponse()?->getStatusCode() : null,
                ]);
            }

            $sent = 0;
            $skipped = 0;
            foreach (config()->array('mail.intake_form_recipients') as $index => $recipient) {
                if (validator(['email' => $recipient], ['email' => 'required|string|email'])->fails()) {
                    $skipped++;
                    Span::getCurrent()->addEvent('email.skipped', ['reason' => 'invalid_recipient']);
                    Log::warning('Skipping PDF email for invalid recipient.', [...$context, 'recipient_index' => $index]);

                    continue;
                }

                $stage = 'email.send';
                $span = Globals::tracerProvider()->getTracer('gooddads-enrollment-bot')
                    ->spanBuilder('email.send')->startSpan();
                $scope = $span->activate();

                try {
                    Mail::to($recipient)->send(new IntakeFormMailable($this->updatedParticipantData));
                    $sent++;
                } catch (Throwable $exception) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);

                    throw $exception;
                } finally {
                    $scope->detach();
                    $span->end();
                }
            }

            Log::info('Participant processing completed.', [
                ...$context,
                'pdf' => 'succeeded',
                'dropbox' => $dropbox,
                'email_sent' => $sent,
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
