<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string|null $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NeonHash newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NeonHash newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NeonHash query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NeonHash whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NeonHash whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NeonHash whereUpdatedAt($value)
 * @mixin \Eloquent
 */
#[Fillable(['id'])]
#[Table(name: 'neon_participant_hashes', keyType: 'string', incrementing: false)]
final class NeonHash extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
}
