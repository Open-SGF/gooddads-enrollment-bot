<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DropboxToken;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use RuntimeException;
use Throwable;

final readonly class DropboxOAuthService
{
    public function __construct(
        private GenericProvider $oauthProvider,
    ) {}

    public function buildAuthorizationUrl(string $state): string
    {
        return $this->oauthProvider->getAuthorizationUrl([
            'state' => $state,
            'token_access_type' => 'offline',
            'approval_prompt' => null,
        ]);
    }

    public function authorize(string $code): DropboxToken
    {
        $token = $this->requestAccessToken(
            'authorization_code',
            ['code' => $code],
            'Unable to complete Dropbox authorization',
        );

        return $this->storeAccessToken($token);
    }

    public function getValidAccessToken(bool $forceRefresh = false): string
    {
        $storedToken = DropboxToken::query()->find(1);

        throw_if(
            $storedToken === null,
            RuntimeException::class,
            'Dropbox has not been authorized yet. Visit /dropbox/authorize to connect the app.',
        );

        $accessToken = $this->readEncryptedTokenValue($storedToken, 'access_token');

        if (! $forceRefresh && $accessToken !== '' && $storedToken->expires_at?->gt(Date::now()->addMinute())) {
            return $accessToken;
        }

        return $this->refreshAccessToken($storedToken);
    }

    private function refreshAccessToken(DropboxToken $storedToken): string
    {
        $refreshToken = $this->readEncryptedTokenValue($storedToken, 'refresh_token');

        throw_if(
            $refreshToken === '',
            RuntimeException::class,
            'Dropbox refresh token is missing. Re-authorize the app at /dropbox/authorize.',
        );

        $token = $this->requestAccessToken(
            'refresh_token',
            ['refresh_token' => $refreshToken],
            'Unable to refresh Dropbox access token',
        );

        $storedToken = $this->storeAccessToken($token, $storedToken, $refreshToken);

        return $this->readEncryptedTokenValue($storedToken, 'access_token');
    }

    /**
     * @param  array<string, string>  $options
     */
    private function requestAccessToken(string $grant, array $options, string $failureMessage): AccessTokenInterface
    {
        try {
            $token = $this->oauthProvider->getAccessToken($grant, $options);
        } catch (Throwable $throwable) {
            Log::error($failureMessage.'.', [
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            throw new RuntimeException($failureMessage.': '.$throwable->getMessage(), $throwable->getCode(), previous: $throwable);
        }

        return $token;
    }

    private function storeAccessToken(
        AccessTokenInterface $token,
        ?DropboxToken $storedToken = null,
        string $existingRefreshToken = '',
    ): DropboxToken {
        $accessToken = $token->getToken();
        $refreshToken = $token->getRefreshToken();
        $expiresAt = $token->getExpires();

        throw_if($accessToken === '', RuntimeException::class, 'Dropbox OAuth response did not include an access token.');

        if ($refreshToken === null || $refreshToken === '') {
            $refreshToken = $existingRefreshToken;
        }

        throw_if($refreshToken === '', RuntimeException::class, 'Dropbox OAuth response did not include a refresh token.');
        throw_unless(is_int($expiresAt) && $expiresAt > Date::now()->timestamp, RuntimeException::class, 'Dropbox OAuth response did not include a valid expiration.');

        $existingTokenType = $storedToken instanceof DropboxToken ? $storedToken->token_type : null;
        $existingScope = $storedToken instanceof DropboxToken ? $storedToken->scope : null;
        $existingAccountId = $storedToken instanceof DropboxToken ? $storedToken->account_id : null;

        $storedToken = DropboxToken::query()->updateOrCreate(
            ['id' => 1],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => Date::createFromTimestamp($expiresAt),
                'token_type' => $this->tokenValue($token, 'token_type') ?? $existingTokenType ?? 'bearer',
                'scope' => $this->tokenValue($token, 'scope') ?? $existingScope,
                'account_id' => $this->tokenValue($token, 'account_id') ?? $existingAccountId,
            ],
        );

        Log::info('Stored Dropbox OAuth tokens.', [
            'account_id' => $storedToken->account_id,
            'expires_at' => $storedToken->expires_at?->toIso8601String(),
        ]);

        return $storedToken;
    }

    private function tokenValue(AccessTokenInterface $token, string $key): ?string
    {
        $values = $token->getValues();
        $value = $values[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function readEncryptedTokenValue(DropboxToken $storedToken, string $attribute): string
    {
        try {
            $value = $storedToken->getAttributeValue($attribute);
        } catch (DecryptException $decryptException) {
            Log::error('Dropbox token decryption failed while reading stored credentials.', [
                'attribute' => $attribute,
                'exception' => $decryptException::class,
                'message' => $decryptException->getMessage(),
            ]);

            throw new RuntimeException('Stored Dropbox credentials cannot be decrypted. If APP_KEY changed, run dropbox:rewrap-tokens with --from-key or re-authorize via /dropbox/authorize.', $decryptException->getCode(), previous: $decryptException);
        }

        return is_string($value) ? $value : '';
    }
}
