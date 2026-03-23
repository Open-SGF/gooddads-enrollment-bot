<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use mikehaertl\pdftk\Pdf;
use App\DTOs\ParticipantUpdateData;

class PdfIntakeFormService
{
    protected string $formKey = 'dad_intake_form';

    protected string $pdfTemplatePath = 'intake-form/Enrollment_Form_Fillable_2026-01-27.pdf';

    public function generate(ParticipantUpdateData $participant): string
    {

        // Build folder structure for each participant
        // NOTE: app/private is prefixed by Laravel
        $storagePath = "participant-forms/{$participant->id}/";
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = Str::of($participant->lastName)->slug('_')->ucfirst().'_'.Str::of($participant->firstName)->slug('_')->ucfirst().'_Enrollment_'.$timestamp.'.pdf';

        $outputPath = Storage::path("{$storagePath}{$filename}");

        // Ensure directory exists
        Storage::makeDirectory($storagePath);
        $data = $participant->toPdfArray();

        // Load and fill the PDF
        $pdf = new Pdf(storage_path("{$this->pdfTemplatePath}"));
        $pdf->fillForm($data)
            ->needAppearances()
            ->flatten()
            ->saveAs($outputPath);

        if (! $pdf->getError()) {
            return "participant-forms/{$participant->id}/".$filename;
        }

        throw new \Exception('PDF generation failed: '.$pdf->getError());
    }
}
