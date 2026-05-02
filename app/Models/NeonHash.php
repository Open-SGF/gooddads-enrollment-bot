<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string|null $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|NeonHash newModelQuery()
 * @method static Builder<static>|NeonHash newQuery()
 * @method static Builder<static>|NeonHash query()
 * @method static Builder<static>|NeonHash whereCreatedAt($value)
 * @method static Builder<static>|NeonHash whereId($value)
 * @method static Builder<static>|NeonHash whereUpdatedAt($value)
 *
 * @mixin Model
 */
#[Fillable(['id'])]
#[Table(name: 'neon_participant_hashes', keyType: 'string', incrementing: false)]
final class NeonHash extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
}
