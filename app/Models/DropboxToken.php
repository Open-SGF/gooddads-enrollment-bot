<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int|null $id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $expires_at
 * @property string|null $token_type
 * @property string|null $scope
 * @property string|null $account_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereTokenType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DropboxToken whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'id',
    'access_token',
    'refresh_token',
    'expires_at',
    'token_type',
    'scope',
    'account_id',
])]
final class DropboxToken extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

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
