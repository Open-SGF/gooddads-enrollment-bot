<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParticipantUpdateData;
use App\Mail\IntakeFormMailable;
use App\Services\DropboxUploadService;
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
use Illuminate\Support\Facades\Storage;

final class GenerateParticipantPdfJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Create a new job instance. */
    public function __construct(
        public readonly ParticipantUpdateData $updatedParticipantData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        PdfIntakeFormService $pdfService,
        DropboxUploadService $dropboxService
    ): void {
        try {
            // Fetch and transform participant data
            // $fullRecord = $neonApi->buildFullParticipantRecord($this->participantId);
            // $participant = $transformer->transformPerson($fullRecord);

            // Generate the PDF
            Log::info('🔄 Generating PDF.');
            $pdfPath = $pdfService->generate($this->updatedParticipantData);
            Log::info('✅ PDF-generation complete');

            // Check if the required participant form fields are filled
            if ($this->updatedParticipantData->hasMissingFields()) {
                // Send email
                Log::warning('⚠️ PDF not generated for participant '.$this->updatedParticipantData->id.': missing required fields', [
                    'missing_fields' => $this->updatedParticipantData->getMissingFields(),
                ]);

            } else {

                // Upload to Dropbox
                try {
                    $dropboxService->upload(Storage::path($pdfPath), $pdfPath);
                    Log::info('✅ Dropbox upload complete.');
                } catch (Exception $e) {
                    Log::warning('⚠️ Dropbox upload failed, skipping. Reason: '.$e->getMessage());
                }

                // Send email
                Log::info('📧 Sending PDF email for participant '.$this->updatedParticipantData->id);
                Mail::to('hello@example.com')
                    ->send(new IntakeFormMailable($this->updatedParticipantData, $pdfPath));
                Log::info('✅ PDF email sent.');
            }

        } catch (Exception $exception) {
            Log::error('Failed to generate PDF for participant '.$this->updatedParticipantData->id.': '.$exception->getMessage());
            throw $exception; // Let the job retry if needed
        }
    }
}
