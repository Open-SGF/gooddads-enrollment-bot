<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

final class NeonHash extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    #[Override]
    public $incrementing = false;

    #[Override]
    protected $keyType = 'string';

    #[Override]
    protected $table = 'neon_participant_hashes';

    #[Override]
    protected $fillable = ['id'];
}
