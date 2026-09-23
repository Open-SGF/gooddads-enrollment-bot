<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Spatie\Dropbox\Client;

final readonly class DropboxUploadService
{
    private string $uploadPath;

    public function __construct(
        private Client $dropboxClient,
    ) {
        $uploadPath = config('services.dropbox.upload_path');

        $this->uploadPath = is_string($uploadPath) ? $uploadPath : '';
    }

    /**
     * Upload raw file contents or a local file to Dropbox.
     *
     * @param  string  $contents  Raw PDF bytes or a local file path to upload
     * @param  string  $dropboxPath  Destination path in Dropbox (e.g., "participant-forms/123/file.pdf")
     * @return array<mixed>
     */
    public function upload(string $contents, string $dropboxPath): array
    {
        $normalizedContents = $this->normalizeUploadContents($contents);

        if ($normalizedContents === '') {
            Log::error('Dropbox upload failed: empty PDF contents.', [
                'dropbox_path' => $dropboxPath,
            ]);
            throw new InvalidArgumentException('Cannot upload empty PDF contents to Dropbox.');
        }

        $fullDropboxPath = mb_rtrim($this->uploadPath, '/').'/'.$dropboxPath;

        Log::debug('Dropbox upload starting.', [
            'payload_size' => mb_strlen($normalizedContents, '8bit'),
            'dropbox_path' => $fullDropboxPath,
        ]);

        $metadata = $this->dropboxClient->upload(
            $fullDropboxPath,
            $normalizedContents,
            mode: 'add',
            autorename: true,
        );

        Log::info('Dropbox upload succeeded.', [
            'dropbox_path' => $metadata['path_display'] ?? $fullDropboxPath,
            'file_id' => $metadata['id'] ?? null,
            'size' => $metadata['size'] ?? null,
        ]);

        return $metadata;
    }

    private function normalizeUploadContents(string $contents): string
    {
        if ($this->looksLikeFilesystemPath($contents)) {
            throw_if(! is_file($contents) || ! is_readable($contents), InvalidArgumentException::class, 'File not found or not readable');

            $fileContents = @file_get_contents($contents);

            throw_if($fileContents === false, InvalidArgumentException::class, 'File not found or not readable');

            return $fileContents;
        }

        return $contents;
    }

    private function looksLikeFilesystemPath(string $value): bool
    {
        if ($value === '' || str_contains($value, "\0")) {
            return false;
        }

        if (is_file($value)) {
            return true;
        }

        return str_starts_with($value, '/')
            || str_starts_with($value, '\\')
            || str_starts_with($value, '~/')
            || preg_match('~^[A-Za-z]:[\\\\/]~', $value) === 1;
    }
}
