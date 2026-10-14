<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParticipantUpdateData;
use App\DTOs\PdfGenerationResultDTO;
use App\Models\ParticipantPdfVersion;
use Illuminate\Support\Facades\DB;

final class ParticipantPdfVersionService
{
    public function generate(
        ParticipantUpdateData $participant,
        PdfIntakeFormService $pdfService,
        string $neonDataHash,
        bool $forceRegeneration = false,
        ?string $jobUuid = null,
    ): ?PdfGenerationResultDTO {
        return DB::transaction(function () use ($participant, $pdfService, $neonDataHash, $forceRegeneration, $jobUuid): ?PdfGenerationResultDTO {
            // Lock one row per participant, including their first generation.
            DB::table('participant_pdf_histories')->upsert(
                [['participant_id' => $participant->id]],
                ['participant_id'],
                ['participant_id'],
            );
            DB::table('participant_pdf_histories')->where('participant_id', $participant->id)->lockForUpdate()->first();

            $previous = ParticipantPdfVersion::query()
                ->where('participant_id', $participant->id)
                ->orderByDesc('version')
                ->first();

            if (! $forceRegeneration && $previous?->neon_data_hash === $neonDataHash) {
                // Failed-job replay resets the attempt count, but retains the UUID.
                $retrying = $jobUuid !== null && ParticipantPdfVersion::query()
                    ->where('participant_id', $participant->id)
                    ->where('job_uuid', $jobUuid)
                    ->exists();

                if (! $retrying) {
                    return null;
                }
            }

            $number = ($previous->version ?? 0) + 1;
            $fieldHashes = $this->fieldHashes($participant);
            $changedFields = $previous instanceof ParticipantPdfVersion
                ? $this->changedFields($previous->fieldHashes(), $fieldHashes)
                : [];

            $result = $pdfService->generate($participant, $number);
            $result->version = ParticipantPdfVersion::query()->create([
                'participant_id' => $participant->id,
                'version' => $number,
                'neon_data_hash' => $neonDataHash,
                'job_uuid' => $jobUuid,
                'field_hashes' => $fieldHashes,
                'changed_fields' => $changedFields,
                'filename' => $result->filename,
            ]);

            return $result;
        }, attempts: 3);
    }

    /** @return array<string, string> */
    private function fieldHashes(ParticipantUpdateData $participant): array
    {
        $hashes = [];
        $fields = [...$participant->toPdfArray(), 'firstName' => $participant->firstName, 'lastName' => $participant->lastName];

        foreach ($fields as $name => $value) {
            $hashes[$name] = hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
        }

        return $hashes;
    }

    /**
     * @param  array<string, string>  $previous
     * @param  array<string, string>  $current
     * @return list<string>
     */
    private function changedFields(array $previous, array $current): array
    {
        $changed = [];
        foreach (array_unique([...array_keys($previous), ...array_keys($current)]) as $field) {
            if (($previous[$field] ?? null) !== ($current[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        sort($changed);

        return $changed;
    }
}
