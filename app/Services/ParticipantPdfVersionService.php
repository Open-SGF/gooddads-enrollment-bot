<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ParticipantUpdateData;
use App\DTOs\PdfGenerationResultDTO;
use App\Models\Participant;
use App\Models\ParticipantPdfVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ParticipantPdfVersionService
{
    public function generate(
        ParticipantUpdateData $participant,
        PdfIntakeFormService $pdfService,
        string $neonDataHash,
        ?string $jobUuid = null,
    ): ?PdfGenerationResultDTO {
        return DB::transaction(function () use ($participant, $pdfService, $neonDataHash, $jobUuid): ?PdfGenerationResultDTO {
            // Lock one row per participant, including their first generation.
            DB::table('participants')->upsert([['id' => $participant->id]], ['id'], ['id']);
            $storedParticipant = Participant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $existing = $jobUuid !== null
                ? $storedParticipant->pdfVersions()->where('job_uuid', $jobUuid)->orderBy('version')->first()
                : null;

            // Recreate the PDF bytes for delivery without advancing the version or fingerprints.
            if ($existing instanceof ParticipantPdfVersion) {
                $result = $pdfService->generate($participant, $existing->version);
                $result->filename = (string) $existing->filename;
                $result->version = $existing;

                return $result;
            }

            $previous = $storedParticipant->pdfVersions()->orderByDesc('version')->first();
            if ($previous?->neon_data_hash === $neonDataHash) {
                return null;
            }

            $number = ($previous->version ?? 0) + 1;
            $fieldHashes = $this->fieldHashes($participant);
            $changedFields = $previous instanceof ParticipantPdfVersion && $storedParticipant->field_hashes !== null
                ? $this->changedFields($storedParticipant->fieldHashes(), $fieldHashes)
                : [];

            $result = $pdfService->generate($participant, $number);
            $result->version = ParticipantPdfVersion::query()->create([
                'participant_id' => $participant->id,
                'version' => $number,
                'neon_data_hash' => $neonDataHash,
                'job_uuid' => $jobUuid,
                'changed_fields' => $changedFields,
                'filename' => $result->filename,
            ]);

            $storedParticipant->update(['field_hashes' => $fieldHashes]);

            return $result;
        }, attempts: 3);
    }

    /** @return array<string, string> */
    private function fieldHashes(ParticipantUpdateData $participant): array
    {
        $key = config('participant_pdf.hash_key');
        throw_if(! is_string($key) || mb_strlen($key) < 32, RuntimeException::class, 'PARTICIPANT_PDF_HASH_KEY must be set to a secret of at least 32 characters.');

        $hashes = [];
        $fields = [...$participant->toPdfArray(), 'firstName' => $participant->firstName, 'lastName' => $participant->lastName];

        foreach ($fields as $name => $value) {
            $hashes[$name] = hash_hmac('sha256', json_encode([$participant->id, $name, $value], JSON_THROW_ON_ERROR), $key);
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
