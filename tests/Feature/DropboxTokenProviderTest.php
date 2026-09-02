<?php

declare(strict_types=1);

use App\Models\DropboxToken;
use App\Services\DropboxTokenProvider;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Spatie\Dropbox\Client;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.dropbox.oauth.clientId', 'app-key');
    config()->set('services.dropbox.oauth.clientSecret', 'app-secret');
    config()->set('services.dropbox.oauth.redirectUri', 'http://localhost:8080/dropbox/callback');

    DropboxToken::query()->create([
        'id' => 1,
        'access_token' => 'current-access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
        'token_type' => 'bearer',
        'scope' => 'files.content.write',
        'account_id' => 'dbid:test-account',
    ]);
});

it('supplies stored OAuth access tokens to the Dropbox client', function (): void {
    $provider = resolve(DropboxTokenProvider::class);
    $client = resolve(Client::class);

    expect($provider->getToken())->toBe('current-access-token')
        ->and($client->getAccessToken())->toBe('current-access-token');
});

it('refreshes the OAuth token after an unauthorized Dropbox response', function (): void {
    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldReceive('getAccessToken')
        ->once()
        ->with('refresh_token', ['refresh_token' => 'refresh-token'])
        ->andReturn(new AccessToken([
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 14400,
            'token_type' => 'bearer',
            'scope' => 'files.content.write',
            'account_id' => 'dbid:test-account',
        ]));
    $this->app->instance(GenericProvider::class, $oauthProvider);

    $exception = new ClientException(
        'Unauthorized',
        new Request('POST', 'https://content.dropboxapi.com/2/files/upload'),
        new Response(401),
    );

    $provider = resolve(DropboxTokenProvider::class);

    expect($provider->refresh($exception))->toBeTrue()
        ->and($provider->getToken())->toBe('refreshed-access-token');

    $token = DropboxToken::query()->findOrFail(1);

    expect($token->refresh_token)->toBe('rotated-refresh-token');
});

it('does not refresh tokens for non-authentication errors', function (): void {
    $oauthProvider = Mockery::mock(GenericProvider::class);
    $oauthProvider->shouldNotReceive('getAccessToken');

    $this->app->instance(GenericProvider::class, $oauthProvider);

    $exception = new ClientException(
        'Conflict',
        new Request('POST', 'https://content.dropboxapi.com/2/files/upload'),
        new Response(409),
    );

    expect(resolve(DropboxTokenProvider::class)->refresh($exception))->toBeFalse();
});
