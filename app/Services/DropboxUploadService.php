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
     * Upload raw file contents to Dropbox.
     *
     * @param  string  $contents  raw PDF bytes to upload
     * @param  string  $dropboxPath  Destination path in Dropbox (e.g., "participant-forms/123/file.pdf")
     * @return array<mixed>
     */
    public function upload(string $contents, string $dropboxPath): array
    {
        if ($contents === '') {
            Log::error('Dropbox upload failed: empty PDF contents.', [
                'dropbox_path' => $dropboxPath,
            ]);
            throw new InvalidArgumentException('Cannot upload empty PDF contents to Dropbox.');
        }

        $fullDropboxPath = mb_rtrim($this->uploadPath, '/').'/'.$dropboxPath;

        Log::debug('Dropbox upload starting.', [
            'payload_size' => mb_strlen($contents, '8bit'),
            'dropbox_path' => $fullDropboxPath,
        ]);

        $metadata = $this->dropboxClient->upload(
            $fullDropboxPath,
            $contents,
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
}
