<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DropboxToken;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class DropboxOAuthService
{
    private string $appKey;

    private string $appSecret;

    private string $redirectUri;

    public function __construct()
    {
        $appKey = config('services.dropbox.app_key');
        $appSecret = config('services.dropbox.app_secret');
        $redirectUri = config('services.dropbox.redirect_uri');

        $this->appKey = is_string($appKey) ? $appKey : '';
        $this->appSecret = is_string($appSecret) ? $appSecret : '';
        $this->redirectUri = is_string($redirectUri) ? $redirectUri : '';
    }

    public function buildAuthorizationUrl(string $state): string
    {
        $this->assertAuthorizationConfiguration();

        Log::info('Building Dropbox OAuth authorization URL.');

        $query = http_build_query([
            'client_id' => $this->appKey,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'state' => $state,
        ]);

        return 'https://www.dropbox.com/oauth2/authorize?'.$query;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $this->assertFullConfiguration();

        Log::info('Exchanging Dropbox OAuth authorization code for tokens.');

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://api.dropboxapi.com/oauth2/token', [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'client_id' => $this->appKey,
                'client_secret' => $this->appSecret,
                'redirect_uri' => $this->redirectUri,
            ]);

        $payload = $this->associativeArray($response->json());

        if (! $response->successful()) {
            $errorSummary = $this->errorSummary($payload);
            Log::error('Dropbox OAuth authorization code exchange failed.', [
                'http_code' => $response->status(),
                'error_summary' => $errorSummary,
            ]);
            throw new RuntimeException('Unable to complete Dropbox authorization: '.$errorSummary);
        }

        Log::info('Dropbox OAuth authorization code exchange succeeded.');

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeAuthorizationTokens(array $payload): DropboxToken
    {
        $refreshToken = $payload['refresh_token'] ?? null;
        $accessToken = $payload['access_token'] ?? null;

        if (! is_string($refreshToken) || $refreshToken === '' || ! is_string($accessToken) || $accessToken === '') {
            Log::error('Dropbox OAuth payload missing required tokens.');
            throw new RuntimeException('Dropbox OAuth response did not include the required tokens.');
        }

        $expiresIn = $this->resolveExpiresIn($payload, 'authorization code exchange');

        $storedToken = DropboxToken::query()->updateOrCreate(
            ['id' => 1],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => Date::now()->addSeconds($expiresIn),
                'token_type' => $this->stringValue($payload, 'token_type', 'bearer'),
                'scope' => $this->nullableStringValue($payload, 'scope'),
                'account_id' => $this->nullableStringValue($payload, 'account_id'),
            ],
        );

        Log::info('Stored Dropbox OAuth tokens.', [
            'account_id' => $storedToken->account_id,
            'expires_at' => $storedToken->expires_at?->toIso8601String(),
        ]);

        return $storedToken;
    }

    public function getValidAccessToken(bool $forceRefresh = false): string
    {
        $storedToken = DropboxToken::query()->find(1);

        if ($storedToken === null) {
            Log::error('Dropbox access token requested but app has not been authorized.');
            throw new RuntimeException('Dropbox has not been authorized yet. Visit /dropbox/authorize to connect the app.');
        }

        $currentAccessToken = $this->readEncryptedTokenValue($storedToken, 'access_token');

        $expiresAt = $storedToken->expires_at;

        if (! $forceRefresh && $currentAccessToken !== '' && $expiresAt !== null && $expiresAt->gt(now()->addSeconds(60))) {
            Log::info('Using existing valid Dropbox access token.', [
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return $currentAccessToken;
        }

        Log::info('Refreshing Dropbox access token.', [
            'force_refresh' => $forceRefresh,
            'expires_at' => $storedToken->expires_at?->toIso8601String(),
        ]);

        return $this->refreshAccessToken($storedToken);
    }

    public function refreshAccessToken(DropboxToken $storedToken): string
    {
        $refreshToken = $this->readEncryptedTokenValue($storedToken, 'refresh_token');

        if ($refreshToken === '') {
            Log::error('Dropbox token refresh failed: refresh token missing.');
            throw new RuntimeException('Dropbox refresh token is missing. Re-authorize the app at /dropbox/authorize.');
        }

        $this->assertFullConfiguration();

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://api.dropboxapi.com/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->appKey,
                'client_secret' => $this->appSecret,
            ]);

        $payload = $this->associativeArray($response->json());

        if (! $response->successful()) {
            $errorSummary = $this->errorSummary($payload);

            Log::error('Dropbox token refresh failed: API error', [
                'http_code' => $response->status(),
                'error_summary' => $errorSummary,
                'response' => $response->body(),
            ]);

            throw new RuntimeException('Unable to refresh Dropbox access token: '.$errorSummary);
        }

        $accessToken = $this->nullableStringValue($payload, 'access_token');

        if ($accessToken === null || $accessToken === '') {
            Log::error('Dropbox token refresh response missing access token.');
            throw new RuntimeException('Dropbox token refresh response did not include an access token.');
        }

        $expiresIn = $this->resolveExpiresIn($payload, 'token refresh');
        $rotatedRefreshToken = $this->nullableStringValue($payload, 'refresh_token');

        $storedToken->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => is_string($rotatedRefreshToken) && $rotatedRefreshToken !== '' ? $rotatedRefreshToken : $refreshToken,
            'expires_at' => Date::now()->addSeconds($expiresIn),
            'token_type' => $this->stringValue($payload, 'token_type', 'bearer'),
            'scope' => $this->nullableStringValue($payload, 'scope') ?? $storedToken->scope,
            'account_id' => $this->nullableStringValue($payload, 'account_id') ?? $storedToken->account_id,
        ])->save();

        Log::info('Dropbox access token refresh succeeded.', [
            'account_id' => $storedToken->account_id,
            'expires_at' => $storedToken->expires_at?->toIso8601String(),
        ]);

        return $this->readEncryptedTokenValue($storedToken, 'access_token');
    }

    public function fetchAccountEmail(string $accessToken): ?string
    {
        if ($accessToken === '') {
            Log::warning('Skipping Dropbox account email lookup because access token is empty.');

            return null;
        }

        Log::info('Resolving Dropbox account email for success view.');

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post('https://api.dropboxapi.com/2/users/get_current_account');

        if (! $response->successful()) {
            Log::warning('Unable to resolve Dropbox account email for success view.', [
                'http_code' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;
        }

        $payload = $this->associativeArray($response->json());
        $email = $this->nullableStringValue($payload, 'email');

        if ($email === null || $email === '') {
            Log::warning('Dropbox account email was not present in account lookup response.');
        } else {
            Log::info('Resolved Dropbox account email for success view.', ['email' => $email]);
        }

        return $email !== '' ? $email : null;
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key, string $default = ''): string
    {
        $value = $payload[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return array<string, mixed> */
    private function associativeArray(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $normalized = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload */
    private function nullableStringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function errorSummary(array $payload): string
    {
        return $this->stringValue($payload, 'error_summary', $this->stringValue($payload, 'error_description', 'Unknown error'));
    }

    private function assertAuthorizationConfiguration(): void
    {
        if ($this->appKey === '' || $this->redirectUri === '') {
            Log::error('Dropbox OAuth authorization configuration is incomplete.', [
                'app_key_configured' => $this->appKey !== '',
                'redirect_uri_configured' => $this->redirectUri !== '',
            ]);
            throw new RuntimeException('Dropbox OAuth configuration is incomplete. Check DROPBOX_APP_KEY and DROPBOX_REDIRECT_URI.');
        }
    }

    private function assertFullConfiguration(): void
    {
        if ($this->appKey === '' || $this->appSecret === '' || $this->redirectUri === '') {
            Log::error('Dropbox OAuth configuration is incomplete for token operations.', [
                'app_key_configured' => $this->appKey !== '',
                'app_secret_configured' => $this->appSecret !== '',
                'redirect_uri_configured' => $this->redirectUri !== '',
            ]);
            throw new RuntimeException('Dropbox OAuth configuration is incomplete. Check DROPBOX_APP_KEY, DROPBOX_APP_SECRET, and DROPBOX_REDIRECT_URI.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveExpiresIn(array $payload, string $context): int
    {
        $rawExpiresIn = $payload['expires_in'] ?? null;
        $expiresIn = is_int($rawExpiresIn)
            ? $rawExpiresIn
            : (is_string($rawExpiresIn) && ctype_digit($rawExpiresIn) ? (int) $rawExpiresIn : 0);

        if ($expiresIn <= 0) {
            Log::error('Dropbox OAuth response included an invalid expires_in value.', [
                'context' => $context,
                'expires_in' => $rawExpiresIn,
            ]);

            throw new RuntimeException('Dropbox '.$context.' response included an invalid expires_in value.');
        }

        return $expiresIn;
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

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }
}
