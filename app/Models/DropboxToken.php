<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class DropboxToken extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

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