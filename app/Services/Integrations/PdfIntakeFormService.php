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

    protected string $pdfTemplatePath = 'enrollment-form/Enrollment_Form_Fillable_2026-01-27.pdf';

    public function generate(ParticipantUpdateData $participant): string
    {
        // $fieldMap = config("pdf_forms.{$this->formKey}");

        // $data = [];
        // foreach ($fieldMap as $pdfField => $participantField) {
        //     $value = data_get($participant, $participantField);
        //     // if ($value instanceof \Carbon\Carbon) {
        //     //     $value = $value->format('m/d/Y');
        //     // }
        //     $data[$pdfField] = $value ?? '';
        // }

        // Build folder structure for each participant
        $storagePath = "participant-forms/{$participant->id}/";
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = Str::of($participant->lastName)->slug('_')->ucfirst().'_'.Str::of($participant->firstName)->slug('_')->ucfirst().'_Enrollment_'.$timestamp.'.pdf';

        $outputPath = storage_path("app/{$storagePath}{$filename}");

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
