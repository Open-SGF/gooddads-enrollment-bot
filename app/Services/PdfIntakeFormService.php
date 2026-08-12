<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParticipantUpdateData;
use App\DTOs\PdfGenerationResultDTO;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use mikehaertl\pdftk\Pdf;

final class PdfIntakeFormService
{
    private string $pdfTemplatePath = 'intake-form/Enrollment_Form_Fillable_2026-01-27.pdf';

    public function generate(ParticipantUpdateData $participant): PdfGenerationResultDTO
    {

        // Build file name structure for each participant
        $timestamp = Date::now()->format('Y-m-d_H-i-s');
        $filename = Str::of($participant->lastName)->slug('_')->ucfirst().'_'.Str::of($participant->firstName)->slug('_')->ucfirst().'_Enrollment_'.$timestamp.'.pdf';

        // Load and fill the PDF
        $data = $participant->toPdfArray();
        $pdf = new Pdf(storage_path($this->pdfTemplatePath));
        $result = $pdf->fillForm($data)
            ->flatten()
            ->execute();

        if ($result === false) {
            throw new Exception('PDF generation failed: '.$pdf->getError());
        }

        // Read the generated PDF content
        $tmpFile = $pdf->getTmpFile();
        $contents = file_get_contents($tmpFile->getFileName()); 

        // Clean up the temporary file
        $tmpFile->delete = false; // Prevent temp file from being deleted twice
        unlink($tmpFile->getFileName());

        return new PdfGenerationResultDTO(
            filename: (string) $filename,
            contents: $contents
        );
        
    }
}
