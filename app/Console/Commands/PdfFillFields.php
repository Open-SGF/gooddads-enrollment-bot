<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use mikehaertl\pdftk\Pdf;
use Override;

final class PdfFillFields extends Command
{
    #[Override]
    protected $signature = 'pdf:fill-fields';

    #[Override]
    protected $description = 'Fill all form fields with their field names for debugging layout';

    public function handle(): int
    {
        $inputPath = storage_path('pdfs/intake-form/Enrollment_Form_Fillable_2026-01-27.pdf');
        $timestamp = now()->format('Ymd_His');
        $outputPath = storage_path(sprintf('pdfs/intake-form/enrollment_documents_field_names-%s.pdf', $timestamp));

        // --- 1️⃣ First instance: Get all field names ---
        $reader = new Pdf($inputPath);
        $fields = $reader->getDataFields();

        if (! $fields) {
            $this->error('No fields found.');

            return 1;
        }

        if ($fields === true) {
            $this->error('Unable to enumerate PDF fields.');

            return 1;
        }

        $this->info('Found '.count($fields).' fields.');

        // --- 2️⃣ Second instance: Fill the fields with their own names ---
        $pdf = new Pdf($inputPath);
        $formData = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            if (! is_string($field['FieldName'] ?? null)) {
                continue;
            }

            $formData[$field['FieldName']] = $field['FieldName'];
        }

        $result = $pdf
            ->fillForm($formData)
            ->needAppearances()
            ->saveAs($outputPath);

        if (! $result) {
            $this->error('Failed to save PDF: '.$pdf->getError());

            return 1;
        }

        $this->info('✅ Filled PDF saved to: '.$outputPath);

        // Optional: store it using Laravel’s Storage
        $contents = file_get_contents($outputPath);

        if ($contents === false) {
            $this->error('Failed to read generated PDF contents.');

            return 1;
        }

        Storage::put(sprintf('forms/enrollment_documents_fillable_gd_global_DRAFT_v1-%s.pdf', $timestamp), $contents);

        return 0;
    }
}
