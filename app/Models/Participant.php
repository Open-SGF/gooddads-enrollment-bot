<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $id
 * @property array<array-key, mixed>|null $field_hashes
 * @property-read Collection<int, ParticipantPdfVersion>|null $pdfVersions
 * @property-read int|null $pdf_versions_count
 * @property-read bool|null $pdf_versions_exists
 *
 * @method static Builder<static>|Participant newModelQuery()
 * @method static Builder<static>|Participant newQuery()
 * @method static Builder<static>|Participant query()
 * @method static Builder<static>|Participant whereFieldHashes($value)
 * @method static Builder<static>|Participant whereId($value)
 *
 * @mixin Model
 */
#[Fillable(['id', 'field_hashes'])]
#[Table(keyType: 'string', incrementing: false, timestamps: false)]
final class Participant extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @return HasMany<ParticipantPdfVersion, $this> */
    public function pdfVersions(): HasMany
    {
        return $this->hasMany(ParticipantPdfVersion::class);
    }

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
        return ['field_hashes' => 'encrypted:array'];
    }
}
