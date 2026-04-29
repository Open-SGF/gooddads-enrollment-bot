<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParticipantUpdateData;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use mikehaertl\pdftk\Pdf;

final class PdfIntakeFormService
{
    private string $pdfTemplatePath = 'intake-form/Enrollment_Form_Fillable_2026-01-27.pdf';

    public function generate(ParticipantUpdateData $participant): string
    {

        // Build folder structure for each participant
        // NOTE: app/private is prefixed by Laravel
        $storagePath = sprintf('participant-forms/%s/', $participant->id);
        $timestamp = Date::now()->format('Y-m-d_H-i-s');
        $filename = Str::of($participant->lastName)->slug('_')->ucfirst().'_'.Str::of($participant->firstName)->slug('_')->ucfirst().'_Enrollment_'.$timestamp.'.pdf';

        $outputPath = Storage::path($storagePath.$filename);

        // Ensure directory exists
        Storage::makeDirectory($storagePath);
        $data = $participant->toPdfArray();

        // Load and fill the PDF
        $pdf = new Pdf(storage_path($this->pdfTemplatePath));
        $pdf->fillForm($data)
            ->needAppearances()
            ->flatten()
            ->saveAs($outputPath);

        if ($pdf->getError() === '' || $pdf->getError() === '0') {
            return sprintf('participant-forms/%s/', $participant->id).$filename;
        }

        throw new Exception('PDF generation failed: '.$pdf->getError());
    }
}
