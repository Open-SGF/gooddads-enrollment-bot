<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DropboxUploadService
{
    private string $accessToken;

    private string $uploadPath;

    public function __construct()
    {
        $this->accessToken = config('services.dropbox.access_token');
        $this->uploadPath = config('services.dropbox.upload_path');
    }

    /**
     * Upload a file to Dropbox.
     *
     * @param  string  $localPath  Absolute path to the file on disk
     * @param  string  $dropboxPath  Destination path in Dropbox (e.g., "participant-forms/123/file.pdf")
     * @return array{success: bool, data: ?array, error: ?string}
     *
     * @throws RuntimeException
     */
    public function upload(string $localPath, string $dropboxPath): array
    {
        if (! file_exists($localPath) || ! is_readable($localPath)) {
            throw new \InvalidArgumentException("File not found or not readable: {$localPath}");
        }

        $fullDropboxPath = rtrim($this->uploadPath, '/').'/'.$dropboxPath;
        $fileContents = file_get_contents($localPath);

        $apiArg = json_encode([
            'path' => $fullDropboxPath,
            'mode' => 'add',
            'autorename' => true,
            'mute' => false,
        ]);

        Log::debug('Dropbox upload starting', [
            'local_path' => $localPath,
            'dropbox_path' => $fullDropboxPath,
            'file_size' => strlen($fileContents),
        ]);

        $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$this->accessToken,
                'Dropbox-API-Arg: '.$apiArg,
                'Content-Type: application/octet-stream',
            ],
            CURLOPT_POSTFIELDS => $fileContents,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Log::error('Dropbox upload failed: curl error', ['error' => $curlError]);
            throw new RuntimeException('Dropbox upload failed: '.$curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorSummary = $data['error_summary'] ?? 'Unknown error';
            Log::error('Dropbox upload failed: API error', [
                'http_code' => $httpCode,
                'error_summary' => $errorSummary,
                'response' => $response,
            ]);
            throw new RuntimeException("Dropbox upload failed ({$httpCode}): {$errorSummary}");
        }

        Log::info('Dropbox upload succeeded', [
            'dropbox_path' => $data['path_display'] ?? $fullDropboxPath,
            'file_id' => $data['id'] ?? null,
            'size' => $data['size'] ?? null,
        ]);

        return ['success' => true, 'data' => $data, 'error' => null];
    }
}
