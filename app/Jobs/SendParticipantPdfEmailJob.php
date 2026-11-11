<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParticipantUpdateData;
use App\Mail\IntakeFormMailable;
use App\Models\ParticipantPdfVersion;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use Throwable;

#[Backoff(10)]
#[Tries(3)]
final class SendParticipantPdfEmailJob implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ParticipantUpdateData $participant,
        public readonly ParticipantPdfVersion $version,
        public readonly string $recipient,
        public readonly ?string $pollId = null,
    ) {}

    public function handle(): void
    {
        $context = [
            'poll_id' => $this->pollId,
            'participant_id' => $this->participant->id,
            'pdf_version' => $this->version->version,
            'job_id' => $this->job?->uuid(),
            'attempt' => $this->job?->attempts(),
        ];
        $span = Globals::tracerProvider()->getTracer('gooddads-enrollment-bot')
            ->spanBuilder('email.send')->startSpan();
        $scope = $span->activate();

        try {
            Mail::to($this->recipient)->send(new IntakeFormMailable($this->participant, $this->version));
            Log::info('Participant email delivered.', $context);
        } catch (Throwable $throwable) {
            $span->recordException($throwable);
            $span->setStatus(StatusCode::STATUS_ERROR);
            Log::error('Participant email delivery failed.', [
                ...$context,
                'exception' => $throwable::class,
                'error_code' => $throwable->getCode() ?: null,
            ]);

            throw $throwable;
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
