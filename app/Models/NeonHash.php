<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class NeonHash extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'neon_participant_hashes';

    protected $fillable = ['id'];
}
