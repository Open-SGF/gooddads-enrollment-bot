<?php

declare(strict_types=1);

use App\Models\DropboxToken;
use App\Services\DropboxOAuthService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.dropbox.app_key', 'app-key');
    config()->set('services.dropbox.app_secret', 'app-secret');
    config()->set('services.dropbox.redirect_uri', 'http://localhost:8080/dropbox/callback');
    config()->set('services.dropbox.upload_path', '/uploads');
});

it('redirects to Dropbox authorization using the callback route', function (): void {
    $response = $this->get(route('dropbox.authorize'));

    $response->assertRedirectContains('https://www.dropbox.com/oauth2/authorize');
    $response->assertRedirectContains('redirect_uri=http%3A%2F%2Flocalhost%3A8080%2Fdropbox%2Fcallback');

    expect(session('dropbox_oauth_state'))->toBeString()->not->toBe('');
});

it('stores Dropbox tokens after a successful callback', function (): void {
    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 14400,
            'token_type' => 'bearer',
            'scope' => 'files.content.write account_info.read',
            'account_id' => 'dbid:test-account',
        ]),
        'https://api.dropboxapi.com/2/users/get_current_account' => Http::response([
            'email' => 'josh.campbell@ajillion.com',
        ]),
    ]);

    $response = $this
        ->withSession(['dropbox_oauth_state' => 'known-state'])
        ->get('/dropbox/callback?code=test-code&state=known-state');

    $response->assertOk();
    $response->assertSee('Dropbox Connected');
    $response->assertSee('You can now close this tab.');
    $response->assertSee('Connected account: josh.campbell@ajillion.com');

    $token = DropboxToken::query()->findOrFail(1);
    $rawTokenRow = DB::table('dropbox_tokens')->where('id', 1)->first();

    expect($token->access_token)->toBe('fresh-access-token');
    expect($token->refresh_token)->toBe('refresh-token');
    expect($token->account_id)->toBe('dbid:test-account');
    expect($rawTokenRow)->not->toBeNull();
    expect($rawTokenRow->access_token)->not->toBe('fresh-access-token');
    expect($rawTokenRow->refresh_token)->not->toBe('refresh-token');
});

it('shows success page without account metadata when email lookup fails', function (): void {
    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 14400,
            'token_type' => 'bearer',
            'scope' => 'files.content.write account_info.read',
            'account_id' => 'dbid:test-account',
        ]),
        'https://api.dropboxapi.com/2/users/get_current_account' => Http::response([], 500),
    ]);

    $response = $this
        ->withSession(['dropbox_oauth_state' => 'known-state'])
        ->get('/dropbox/callback?code=test-code&state=known-state');

    $response->assertOk();
    $response->assertSee('Dropbox Connected');
    $response->assertSee('You can now close this tab.');
    $response->assertDontSee('Connected account: dbid:');
    $response->assertDontSee('Access token expires at');
});

it('rejects a callback with the wrong state', function (): void {
    $response = $this
        ->withSession(['dropbox_oauth_state' => 'known-state'])
        ->get('/dropbox/callback?code=test-code&state=wrong-state');

    $response->assertForbidden();
});

it('rejects a callback that contains a Dropbox error', function (): void {
    $response = $this
        ->withSession(['dropbox_oauth_state' => 'known-state'])
        ->get('/dropbox/callback?error=access_denied&state=known-state');

    $response->assertStatus(400);
});

it('rejects a callback without an authorization code', function (): void {
    $response = $this
        ->withSession(['dropbox_oauth_state' => 'known-state'])
        ->get('/dropbox/callback?state=known-state');

    $response->assertStatus(400);
});

it('registers throttling middleware for Dropbox callback route', function (): void {
    $route = Route::getRoutes()->getByName('dropbox.callback');

    expect($route)->not->toBeNull();
    expect($route?->gatherMiddleware())->toContain('throttle:30,1');
});

it('refreshes an expired Dropbox access token', function (): void {
    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'expired-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->subMinute(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response([
            'access_token' => 'refreshed-token',
            'expires_in' => 14400,
            'token_type' => 'bearer',
            'scope' => 'files.content.write',
            'account_id' => 'dbid:test-account',
        ]),
    ]);

    $service = app(DropboxOAuthService::class);

    expect($service->getValidAccessToken())->toBe('refreshed-token');

    $token = DropboxToken::query()->findOrFail(1);
    $rawTokenRow = DB::table('dropbox_tokens')->where('id', 1)->first();

    expect($token->access_token)->toBe('refreshed-token');
    expect($token->expires_at->isFuture())->toBeTrue();
    expect($rawTokenRow)->not->toBeNull();
    expect($rawTokenRow->access_token)->not->toBe('refreshed-token');
});

it('persists rotated Dropbox refresh token when returned by refresh response', function (): void {
    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'expired-token',
        'refresh_token' => 'refresh-token-original',
        'expires_at' => now()->subMinute(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response([
            'access_token' => 'refreshed-token',
            'refresh_token' => 'refresh-token-rotated',
            'expires_in' => 14400,
            'token_type' => 'bearer',
            'scope' => 'files.content.write',
            'account_id' => 'dbid:test-account',
        ]),
    ]);

    $service = app(DropboxOAuthService::class);

    expect($service->getValidAccessToken())->toBe('refreshed-token');

    $token = DropboxToken::query()->findOrFail(1);
    $rawTokenRow = DB::table('dropbox_tokens')->where('id', 1)->first();

    expect($token->refresh_token)->toBe('refresh-token-rotated');
    expect($rawTokenRow)->not->toBeNull();
    expect($rawTokenRow->refresh_token)->not->toBe('refresh-token-rotated');
});

it('throws an actionable error when stored access token cannot be decrypted', function (): void {
    $oldAppKey = 'base64:'.base64_encode(random_bytes(32));
    $cipher = (string) config('app.cipher', 'AES-256-CBC');
    $oldKey = base64_decode(substr($oldAppKey, 7), true);

    expect($oldKey)->toBeString();

    $oldEncrypter = new Encrypter((string) $oldKey, $cipher);

    DB::table('dropbox_tokens')->insert([
        'id' => 1,
        'access_token' => $oldEncrypter->encryptString('old-key-access-token'),
        'refresh_token' => $oldEncrypter->encryptString('old-key-refresh-token'),
        'expires_at' => now()->addHour(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(DropboxOAuthService::class);

    expect(fn () => $service->getValidAccessToken())
        ->toThrow(RuntimeException::class, 'Stored Dropbox credentials cannot be decrypted');
});

it('throws an actionable error when stored refresh token cannot be decrypted', function (): void {
    $oldAppKey = 'base64:'.base64_encode(random_bytes(32));
    $cipher = (string) config('app.cipher', 'AES-256-CBC');
    $oldKey = base64_decode(substr($oldAppKey, 7), true);

    expect($oldKey)->toBeString();

    $oldEncrypter = new Encrypter((string) $oldKey, $cipher);

    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'still-valid-access-token',
        'refresh_token' => 'still-valid-refresh-token',
        'expires_at' => now()->subMinute(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    DB::table('dropbox_tokens')->where('id', 1)->update([
        'refresh_token' => $oldEncrypter->encryptString('old-key-refresh-token'),
    ]);

    $service = app(DropboxOAuthService::class);

    expect(fn () => $service->getValidAccessToken(forceRefresh: true))
        ->toThrow(RuntimeException::class, 'Stored Dropbox credentials cannot be decrypted');
});

it('rejects authorization payload when expires_in is invalid', function (): void {
    $service = app(DropboxOAuthService::class);

    expect(fn () => $service->storeAuthorizationTokens([
        'access_token' => 'fresh-access-token',
        'refresh_token' => 'refresh-token',
        'expires_in' => 0,
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]))->toThrow(RuntimeException::class, 'invalid expires_in');
});

it('rejects refresh payload when expires_in is invalid', function (): void {
    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'expired-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->subMinute(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    Http::fake([
        'https://api.dropboxapi.com/oauth2/token' => Http::response([
            'access_token' => 'refreshed-token',
            'token_type' => 'bearer',
            'scope' => 'files.content.write',
            'account_id' => 'dbid:test-account',
        ]),
    ]);

    $service = app(DropboxOAuthService::class);

    expect(fn () => $service->getValidAccessToken())
        ->toThrow(RuntimeException::class, 'invalid expires_in');
});

it('clears stored Dropbox tokens with the reset command', function (): void {
    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'existing-access-token',
        'refresh_token' => 'existing-refresh-token',
        'expires_at' => now()->addHour(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);

    $this->artisan('dropbox:reset-auth --force')
        ->expectsOutputToContain('Deleted 1 row(s) from dropbox_tokens.')
        ->assertSuccessful();

    expect(DropboxToken::query()->count())->toBe(0);
});

it('can also clear sessions with the reset command', function (): void {
    DB::table('sessions')->insert([
        'id' => 'session-for-dropbox-reset-test',
        'user_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode('test-payload'),
        'last_activity' => now()->timestamp,
    ]);

    $this->artisan('dropbox:reset-auth --force --with-sessions')
        ->expectsOutputToContain('Cleared sessions table.')
        ->assertSuccessful();

    expect(DB::table('sessions')->count())->toBe(0);
});

it('reports when no token rows exist during auth reset', function (): void {
    $this->artisan('dropbox:reset-auth --force')
        ->expectsOutputToContain('No rows found in dropbox_tokens.')
        ->assertSuccessful();
});

it('rewraps Dropbox tokens using a previous app key', function (): void {
    $oldAppKey = 'base64:'.base64_encode(random_bytes(32));
    $cipher = (string) config('app.cipher', 'AES-256-CBC');
    $oldKey = base64_decode(substr($oldAppKey, 7), true);

    expect($oldKey)->toBeString();

    $oldEncrypter = new Encrypter((string) $oldKey, $cipher);

    DB::table('dropbox_tokens')->insert([
        'id' => 1,
        'access_token' => $oldEncrypter->encryptString('old-key-access-token'),
        'refresh_token' => $oldEncrypter->encryptString('old-key-refresh-token'),
        'expires_at' => now()->addHour(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $before = DB::table('dropbox_tokens')->where('id', 1)->first();

    $this->artisan('dropbox:rewrap-tokens --force --from-key='.$oldAppKey)
        ->expectsOutputToContain('Re-encrypted 1 row(s) in dropbox_tokens with the current APP_KEY.')
        ->assertSuccessful();

    $after = DB::table('dropbox_tokens')->where('id', 1)->first();
    $token = DropboxToken::query()->findOrFail(1);

    expect($after)->not->toBeNull();
    expect($before)->not->toBeNull();
    expect($after->access_token)->not->toBe($before->access_token);
    expect($after->refresh_token)->not->toBe($before->refresh_token);
    expect($token->access_token)->toBe('old-key-access-token');
    expect($token->refresh_token)->toBe('old-key-refresh-token');
});

it('requires from-key for Dropbox token rewrap command', function (): void {
    $this->artisan('dropbox:rewrap-tokens --force')
        ->expectsOutputToContain('Missing required option: --from-key=<previous APP_KEY>')
        ->assertFailed();
});