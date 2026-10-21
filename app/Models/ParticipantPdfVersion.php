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
 * @property string|null $job_uuid
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
 * @method static Builder<static>|ParticipantPdfVersion whereFilename($value)
 * @method static Builder<static>|ParticipantPdfVersion whereId($value)
 * @method static Builder<static>|ParticipantPdfVersion whereJobUuid($value)
 * @method static Builder<static>|ParticipantPdfVersion whereNeonDataHash($value)
 * @method static Builder<static>|ParticipantPdfVersion whereParticipantId($value)
 * @method static Builder<static>|ParticipantPdfVersion whereUpdatedAt($value)
 * @method static Builder<static>|ParticipantPdfVersion whereVersion($value)
 *
 * @mixin Model
 */
#[Fillable(['participant_id', 'version', 'neon_data_hash', 'job_uuid', 'changed_fields', 'filename'])]
final class ParticipantPdfVersion extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'changed_fields' => 'array',
        ];
    }
}
