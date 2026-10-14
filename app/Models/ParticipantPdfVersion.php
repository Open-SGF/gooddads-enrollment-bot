<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int|null $id
 * @property string|null $participant_id
 * @property int|null $version
 * @property string|null $neon_data_hash
 * @property array<array-key, mixed>|null $field_hashes
 * @property array<array-key, mixed>|null $changed_fields
 * @property string|null $filename
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|ParticipantPdfVersion newModelQuery()
 * @method static Builder<static>|ParticipantPdfVersion newQuery()
 * @method static Builder<static>|ParticipantPdfVersion query()
 * @method static Builder<static>|ParticipantPdfVersion whereChangedFields($value)
 * @method static Builder<static>|ParticipantPdfVersion whereCreatedAt($value)
 * @method static Builder<static>|ParticipantPdfVersion whereFieldHashes($value)
 * @method static Builder<static>|ParticipantPdfVersion whereFilename($value)
 * @method static Builder<static>|ParticipantPdfVersion whereId($value)
 * @method static Builder<static>|ParticipantPdfVersion whereNeonDataHash($value)
 * @method static Builder<static>|ParticipantPdfVersion whereParticipantId($value)
 * @method static Builder<static>|ParticipantPdfVersion whereUpdatedAt($value)
 * @method static Builder<static>|ParticipantPdfVersion whereVersion($value)
 *
 * @mixin Model
 */
#[Fillable(['participant_id', 'version', 'neon_data_hash', 'field_hashes', 'changed_fields', 'filename'])]
final class ParticipantPdfVersion extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @return array<string, string> */
    public function fieldHashes(): array
    {
        $hashes = [];
        foreach ($this->field_hashes ?? [] as $field => $hash) {
            if (is_string($field) && is_string($hash)) {
                $hashes[$field] = $hash;
            }
        }

        return $hashes;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'field_hashes' => 'encrypted:array',
            'changed_fields' => 'array',
        ];
    }
}
