<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id'])]
#[Table(name: 'neon_participant_hashes', keyType: 'string', incrementing: false)]
final class NeonHash extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
}
