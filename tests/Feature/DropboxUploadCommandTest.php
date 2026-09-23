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

it('rejects upload requests for missing local files before calling Dropbox', function (): void {
    $client = Mockery::mock(Client::class);
    $client->shouldNotReceive('upload');

    $service = new DropboxUploadService($client);

    expect(fn (): array => $service->upload('/tmp/does-not-exist-dropbox-test.pdf', 'dropbox-test/missing-file.pdf'))
        ->toThrow(InvalidArgumentException::class, 'File not found or not readable');
});

it('uploads through the Dropbox client with atomic autorename enabled', function (): void {
    Storage::put('dropbox-test/upload-probe.txt', 'probe content');
    $absoluteLocalPath = Storage::path('dropbox-test/upload-probe.txt');

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
    $metadata = $service->upload($absoluteLocalPath, 'dropbox-test/upload-probe.txt');

    expect($metadata)
        ->toHaveKey('id', 'id:dropbox-file-id')
        ->toHaveKey('path_display', '/uploads/dropbox-test/upload-probe (1).txt');

    Storage::delete('dropbox-test/upload-probe.txt');
});

it('propagates errors from the Dropbox client', function (): void {
    Storage::put('dropbox-test/upload-fail-probe.txt', 'failure probe content');
    $absoluteLocalPath = Storage::path('dropbox-test/upload-fail-probe.txt');

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('upload')
        ->once()
        ->with('/uploads/dropbox-test/upload-fail-probe.txt', 'failure probe content', 'add', true)
        ->andThrow(new RuntimeException('Dropbox API request failed'));

    $service = new DropboxUploadService($client);

    expect(fn (): array => $service->upload($absoluteLocalPath, 'dropbox-test/upload-fail-probe.txt'))
        ->toThrow(RuntimeException::class, 'Dropbox API request failed');

    Storage::delete('dropbox-test/upload-fail-probe.txt');
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

it('rejects missing Windows file paths before calling Dropbox', function (string $path): void {
    $client = Mockery::mock(Client::class);
    $client->shouldNotReceive('upload');

    expect(fn (): array => new DropboxUploadService($client)->upload($path, 'missing.pdf'))
        ->toThrow(InvalidArgumentException::class, 'File not found or not readable');
})->with(['C:/missing-intake.pdf', 'C:\\missing-intake.pdf']);
