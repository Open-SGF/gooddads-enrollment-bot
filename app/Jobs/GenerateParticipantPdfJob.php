<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParticipantUpdateData;
use App\Mail\IntakeFormMailable;
use App\Services\PdfIntakeFormService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class GenerateParticipantPdfJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  ChildDTO[]  $children
     */
    public function __construct(
        public readonly ParticipantUpdateData $updatedParticipantData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        PdfIntakeFormService $pdfService
    ) {
        try {
            // Fetch and transform participant data
            // $fullRecord = $neonApi->buildFullParticipantRecord($this->participantId);
            // $participant = $transformer->transformPerson($fullRecord);

            // Generate the PDF
            $pdfPath = $pdfService->generate($this->updatedParticipantData);

            // Send email
            Log::info('📧 Sending PDF email for participant '.$this->updatedParticipantData->id);
            Mail::to('hello@example.com')
                ->send(new IntakeFormMailable($this->updatedParticipantData, $pdfPath));
            Log::info('✅ PDF email sent.');

        } catch (Exception $e) {
            Log::error('Failed to generate PDF for participant '.$this->updatedParticipantData->id.': '.$e->getMessage());
            throw $e; // Let the job retry if needed
        }
    }
}
