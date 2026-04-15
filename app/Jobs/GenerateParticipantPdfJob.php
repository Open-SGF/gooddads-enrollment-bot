<?php

declare(strict_types=1);

namespace App\Jobs;

use App\DTOs\ParticipantUpdateData;
use App\Mail\IntakeFormMailable;
use App\Mail\IncompleteIntakeFormMailable;
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
        PdfIntakeFormService $pdfService,
        DropboxUploadService $dropboxService
    ) {
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
                Log::info('📧 Sending email notification about incomplete intake form for participant ' . $this->updatedParticipantData->id);
                Mail::to('hello@example.com')
                    ->send(new IncompleteIntakeFormMailable($this->updatedParticipantData, $this->updatedParticipantData->getMissingFields()));
                Log::info('✅ Incomplete intake form email sent.');

            } else {

                // Upload to Dropbox
                try {
                    $dropboxService->upload(Storage::path($pdfPath), $pdfPath);
                    Log::info('✅ Dropbox upload complete.');
                } catch (\Exception $e) {
                    Log::warning('⚠️ Dropbox upload failed, skipping. Reason: '.$e->getMessage());
                }

                // Send email
                Log::info('📧 Sending PDF email for participant '.$this->updatedParticipantData->id);
                Mail::to('hello@example.com')
                    ->send(new IntakeFormMailable($this->updatedParticipantData, $pdfPath));
                Log::info('✅ PDF email sent.');
            }

        } catch (Exception $e) {
            Log::error('Failed to generate PDF for participant '.$this->updatedParticipantData->id.': '.$e->getMessage());
            throw $e; // Let the job retry if needed
        }
    }
}
