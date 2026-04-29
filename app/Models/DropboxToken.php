<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/** @property Carbon|null $expires_at */
final class DropboxToken extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    #[Override]
    public $incrementing = false;

    #[Override]
    protected $keyType = 'int';

    #[Override]
    protected $fillable = [
        'id',
        'access_token',
        'refresh_token',
        'expires_at',
        'token_type',
        'scope',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }
}
