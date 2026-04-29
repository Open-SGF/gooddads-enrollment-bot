<?php

declare(strict_types=1);

use App\Models\DropboxToken;
use App\Services\DropboxOAuthService;
use App\Services\DropboxUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.dropbox.app_key', 'app-key');
    config()->set('services.dropbox.app_secret', 'app-secret');
    config()->set('services.dropbox.redirect_uri', 'http://localhost:8080/dropbox/callback');
    config()->set('services.dropbox.upload_path', '/uploads');
});

it('fails when forcing token expiration without a stored Dropbox token', function (): void {
    $this->artisan('dropbox:test-upload --expire-token')
        ->expectsOutputToContain('No Dropbox token is stored yet. Complete the OAuth flow first.')
        ->assertFailed();
});

it('rejects upload requests for missing local files before calling Dropbox', function (): void {
    $service = resolve(DropboxUploadService::class);

    expect(fn () => $service->upload('/tmp/does-not-exist-dropbox-test.pdf', 'dropbox-test/missing-file.pdf'))
        ->toThrow(InvalidArgumentException::class, 'File not found or not readable');
});

it('retries upload after a 401 by forcing token refresh', function (): void {
    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'current-access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response([
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 14400,
            'token_type' => 'bearer',
            'scope' => 'files.content.write',
            'account_id' => 'dbid:test-account',
        ]),
    ]);

    Storage::put('dropbox-test/upload-retry-probe.txt', 'retry probe content');
    $absoluteLocalPath = Storage::path('dropbox-test/upload-retry-probe.txt');

    $uploadCalls = 0;

    $service = new DropboxUploadService(
        resolve(DropboxOAuthService::class),
        function (string $fileContents, string $apiArg, string $accessToken, string $dropboxPath) use (&$uploadCalls): array {
            $uploadCalls++;

            if ($uploadCalls === 1) {
                return [
                    'response' => json_encode(['error_summary' => 'expired_access_token']),
                    'http_code' => 401,
                    'curl_error' => null,
                ];
            }

            return [
                'response' => json_encode([
                    'id' => 'id:dropbox-file-id',
                    'path_display' => '/uploads/dropbox-test/upload-retry-probe.txt',
                    'size' => 20,
                ]),
                'http_code' => 200,
                'curl_error' => null,
            ];
        },
    );

    $result = $service->upload($absoluteLocalPath, 'dropbox-test/upload-retry-probe.txt');

    expect($result['success'])->toBeTrue();
    expect($uploadCalls)->toBe(2);

    Storage::delete('dropbox-test/upload-retry-probe.txt');
});

it('throws a runtime exception when Dropbox upload API returns an error status', function (): void {
    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'current-access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    Storage::put('dropbox-test/upload-fail-probe.txt', 'failure probe content');
    $absoluteLocalPath = Storage::path('dropbox-test/upload-fail-probe.txt');

    $service = new DropboxUploadService(
        resolve(DropboxOAuthService::class),
        fn (string $fileContents, string $apiArg, string $accessToken, string $dropboxPath): array => [
            'response' => json_encode(['error_summary' => 'too_many_write_operations']),
            'http_code' => 429,
            'curl_error' => null,
        ],
    );

    expect(fn (): array => $service->upload($absoluteLocalPath, 'dropbox-test/upload-fail-probe.txt'))
        ->toThrow(RuntimeException::class, 'Dropbox upload failed (429): too_many_write_operations');

    Storage::delete('dropbox-test/upload-fail-probe.txt');
});
