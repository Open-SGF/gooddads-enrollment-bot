<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
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
     * Upload a file to Dropbox.
     *
     * @param  string  $localPath  Absolute path to the file on disk
     * @param  string  $dropboxPath  Destination path in Dropbox (e.g., "participant-forms/123/file.pdf")
     * @return array<mixed>
     */
    public function upload(string $localPath, string $dropboxPath): array
    {
        throw_if(! file_exists($localPath) || ! is_readable($localPath), InvalidArgumentException::class, 'File not found or not readable: '.$localPath);

        $file = fopen($localPath, 'rb');

        throw_if($file === false, RuntimeException::class, 'Unable to read local file for Dropbox upload.');

        $fullDropboxPath = mb_rtrim($this->uploadPath, '/').'/'.$dropboxPath;

        Log::debug('Dropbox upload starting.', [
            'local_path' => $localPath,
            'dropbox_path' => $fullDropboxPath,
            'file_size' => filesize($localPath),
        ]);

        try {
            $metadata = $this->dropboxClient->upload(
                $fullDropboxPath,
                $file,
                mode: 'add',
                autorename: true,
            );
        } finally {
            fclose($file);
        }

        Log::info('Dropbox upload succeeded.', [
            'dropbox_path' => $metadata['path_display'] ?? $fullDropboxPath,
            'file_id' => $metadata['id'] ?? null,
            'size' => $metadata['size'] ?? null,
        ]);

        return $metadata;
    }
}
