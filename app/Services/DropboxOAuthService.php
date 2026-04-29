<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DropboxToken;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DropboxOAuthService
{
    private string $appKey;

    private string $appSecret;

    private string $redirectUri;

    public function __construct()
    {
        $this->appKey = (string) config('services.dropbox.app_key');
        $this->appSecret = (string) config('services.dropbox.app_secret');
        $this->redirectUri = (string) config('services.dropbox.redirect_uri');
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

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            $errorSummary = is_array($payload) ? ($payload['error_summary'] ?? $payload['error_description'] ?? 'Unknown error') : 'Unknown error';
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
                'expires_at' => Carbon::now()->addSeconds($expiresIn),
                'token_type' => (string) ($payload['token_type'] ?? 'bearer'),
                'scope' => $payload['scope'] ?? null,
                'account_id' => $payload['account_id'] ?? null,
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

        if (! $forceRefresh && $currentAccessToken !== '' && $storedToken->expires_at->gt(now()->addSeconds(60))) {
            Log::info('Using existing valid Dropbox access token.', [
                'expires_at' => $storedToken->expires_at?->toIso8601String(),
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

        $payload = $response->json();

        if (! $response->successful()) {
            $errorSummary = is_array($payload) ? ($payload['error_summary'] ?? $payload['error_description'] ?? 'Unknown error') : 'Unknown error';

            Log::error('Dropbox token refresh failed: API error', [
                'http_code' => $response->status(),
                'error_summary' => $errorSummary,
                'response' => $response->body(),
            ]);

            throw new RuntimeException('Unable to refresh Dropbox access token: '.$errorSummary);
        }

        $accessToken = is_array($payload) ? ($payload['access_token'] ?? null) : null;

        if (! is_string($accessToken) || $accessToken === '') {
            Log::error('Dropbox token refresh response missing access token.');
            throw new RuntimeException('Dropbox token refresh response did not include an access token.');
        }

        $expiresIn = $this->resolveExpiresIn($payload, 'token refresh');
        $rotatedRefreshToken = is_array($payload) ? ($payload['refresh_token'] ?? null) : null;

        $storedToken->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => is_string($rotatedRefreshToken) && $rotatedRefreshToken !== '' ? $rotatedRefreshToken : $refreshToken,
            'expires_at' => Carbon::now()->addSeconds($expiresIn),
            'token_type' => is_array($payload) ? (string) ($payload['token_type'] ?? 'bearer') : 'bearer',
            'scope' => is_array($payload) ? ($payload['scope'] ?? $storedToken->scope) : $storedToken->scope,
            'account_id' => is_array($payload) ? ($payload['account_id'] ?? $storedToken->account_id) : $storedToken->account_id,
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

        $payload = $response->json();
        $email = is_array($payload) ? ($payload['email'] ?? null) : null;

        if (! is_string($email) || $email === '') {
            Log::warning('Dropbox account email was not present in account lookup response.');
        } else {
            Log::info('Resolved Dropbox account email for success view.', ['email' => $email]);
        }

        return is_string($email) && $email !== '' ? $email : null;
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
     * @param  array<string, mixed>|mixed  $payload
     */
    private function resolveExpiresIn(mixed $payload, string $context): int
    {
        $expiresIn = is_array($payload) ? (int) ($payload['expires_in'] ?? 0) : 0;

        if ($expiresIn <= 0) {
            Log::error('Dropbox OAuth response included an invalid expires_in value.', [
                'context' => $context,
                'expires_in' => is_array($payload) ? ($payload['expires_in'] ?? null) : null,
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

            throw new RuntimeException(
                'Stored Dropbox credentials cannot be decrypted. If APP_KEY changed, run dropbox:rewrap-tokens with --from-key or re-authorize via /dropbox/authorize.',
                previous: $decryptException,
            );
        }

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }
}