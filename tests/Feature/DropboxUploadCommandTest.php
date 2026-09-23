<?php

declare(strict_types=1);

use App\Services\DropboxUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Dropbox\Client;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.dropbox.oauth.clientId', 'app-key');
    config()->set('services.dropbox.oauth.clientSecret', 'app-secret');
    config()->set('services.dropbox.oauth.redirectUri', 'http://localhost:8080/dropbox/callback');
    config()->set('services.dropbox.upload_path', '/uploads');
});

it('fails when forcing token expiration without a stored Dropbox token', function (): void {
    $this->artisan('dropbox:test-upload --expire-token')
        ->expectsOutputToContain('No Dropbox token is stored yet. Complete the OAuth flow first.')
        ->assertFailed();
});

it('uploads the command probe from memory without using local storage', function (bool $customRemote): void {
    $this->freezeTime();
    Storage::shouldReceive('put', 'path', 'delete')->never();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('upload')->once()
        ->withArgs(function (string $path, string $contents, string $mode, bool $autorename) use ($customRemote): bool {
            $remotePath = mb_substr($path, mb_strlen('/uploads/'));

            return str_starts_with($path, '/uploads/dropbox-test/')
                && (! $customRemote || $remotePath === 'dropbox-test/custom.txt')
                && $contents === implode(PHP_EOL, [
                    'Dropbox upload validation probe',
                    'Generated at: '.now()->toIso8601String(),
                    'Remote path: '.$remotePath,
                ])
                && $mode === 'add'
                && $autorename;
        })
        ->andReturn(['path_display' => '/uploads/dropbox-test/uploaded.txt']);
    $this->app->instance(Client::class, $client);

    $this->artisan('dropbox:test-upload', $customRemote ? ['--remote' => 'dropbox-test/custom.txt'] : [])
        ->expectsOutputToContain('Dropbox upload succeeded.')
        ->expectsOutputToContain('Remote path: /uploads/dropbox-test/uploaded.txt')
        ->assertSuccessful();
})->with([false, true]);

it('uploads through the Dropbox client with atomic autorename enabled', function (): void {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('upload')
        ->once()
        ->withArgs(fn (string $path, string $contents, string $mode, bool $autorename): bool => $path === '/uploads/dropbox-test/upload-probe.txt'
            && $contents === 'probe content'
            && $mode === 'add'
            && $autorename)
        ->andReturn([
            'id' => 'id:dropbox-file-id',
            'path_display' => '/uploads/dropbox-test/upload-probe (1).txt',
            'size' => 13,
        ]);

    $service = new DropboxUploadService($client);
    $metadata = $service->upload('probe content', 'dropbox-test/upload-probe.txt');

    expect($metadata)
        ->toHaveKey('id', 'id:dropbox-file-id')
        ->toHaveKey('path_display', '/uploads/dropbox-test/upload-probe (1).txt');
});

it('propagates errors from the Dropbox client', function (): void {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('upload')
        ->once()
        ->with('/uploads/dropbox-test/upload-fail-probe.txt', 'failure probe content', 'add', true)
        ->andThrow(new RuntimeException('Dropbox API request failed'));

    $service = new DropboxUploadService($client);

    expect(fn (): array => $service->upload('failure probe content', 'dropbox-test/upload-fail-probe.txt'))
        ->toThrow(RuntimeException::class, 'Dropbox API request failed');
});

it('uploads raw PDF bytes without changing the binary contents', function (): void {
    $contents = "%PDF-1.4\n\0\xFF\x80 binary PDF contents";
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('upload')->once()
        ->with('/uploads/participant-forms/123/intake.pdf', $contents, 'add', true)
        ->andReturn(['id' => 'id:raw-pdf']);

    $metadata = new DropboxUploadService($client)->upload($contents, 'participant-forms/123/intake.pdf');

    expect($metadata)->toHaveKey('id', 'id:raw-pdf');
});

it('rejects empty PDF contents before calling Dropbox', function (): void {
    $client = Mockery::mock(Client::class);
    $client->shouldNotReceive('upload');

    expect(fn (): array => new DropboxUploadService($client)->upload('', 'empty.pdf'))
        ->toThrow(InvalidArgumentException::class, 'Cannot upload empty PDF contents');
});

it('treats payloads resembling local paths as literal contents without reading files', function (): void {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('upload')->once()
        ->with('/uploads/literal.txt', __FILE__, 'add', true)
        ->andReturn(['id' => 'id:literal']);

    expect(new DropboxUploadService($client)->upload(__FILE__, 'literal.txt'))
        ->toHaveKey('id', 'id:literal');
});
