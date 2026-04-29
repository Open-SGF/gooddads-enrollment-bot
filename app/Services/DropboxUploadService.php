<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final readonly class DropboxUploadService
{
    private string $uploadPath;

    /**
     * @param  (Closure(string, string, string, string): array{response: string|null, http_code: int, curl_error: string|null})|null  $uploadExecutor
     */
    public function __construct(
        private DropboxOAuthService $dropboxOAuthService,
        private ?Closure $uploadExecutor = null,
    ) {
        $uploadPath = config('services.dropbox.upload_path');

        $this->uploadPath = is_string($uploadPath) ? $uploadPath : '';
    }

    /**
     * Upload a file to Dropbox.
     *
     * @param  string  $localPath  Absolute path to the file on disk
     * @param  string  $dropboxPath  Destination path in Dropbox (e.g., "participant-forms/123/file.pdf")
     * @return array{success: bool, data: array<string, mixed>|null, error: ?string}
     *
     * @throws RuntimeException
     */
    public function upload(string $localPath, string $dropboxPath): array
    {
        if (! file_exists($localPath) || ! is_readable($localPath)) {
            Log::error('Dropbox upload failed before request: local file missing/unreadable.', [
                'local_path' => $localPath,
            ]);
            throw new InvalidArgumentException('File not found or not readable: '.$localPath);
        }

        $fullDropboxPath = mb_rtrim($this->uploadPath, '/').'/'.$dropboxPath;
        $fileContents = file_get_contents($localPath);

        if ($fileContents === false) {
            Log::error('Dropbox upload failed before request: unable to read local file contents.', [
                'local_path' => $localPath,
            ]);
            throw new RuntimeException('Unable to read local file contents for Dropbox upload.');
        }

        $apiArg = json_encode([
            'path' => $fullDropboxPath,
            'mode' => 'add',
            'autorename' => true,
            'mute' => false,
        ]);

        if (! is_string($apiArg)) {
            Log::error('Dropbox upload failed before request: unable to encode Dropbox API arguments.', [
                'dropbox_path' => $fullDropboxPath,
            ]);
            throw new RuntimeException('Unable to encode Dropbox upload metadata.');
        }

        Log::debug('Dropbox upload starting', [
            'local_path' => $localPath,
            'dropbox_path' => $fullDropboxPath,
            'file_size' => mb_strlen($fileContents),
        ]);

        $accessToken = $this->dropboxOAuthService->getValidAccessToken();

        $result = $this->performUpload($fileContents, $apiArg, $accessToken, $fullDropboxPath);

        if ($result['http_code'] === 401) {
            Log::warning('Dropbox access token expired during upload, refreshing and retrying.', [
                'dropbox_path' => $fullDropboxPath,
            ]);

            $accessToken = $this->dropboxOAuthService->getValidAccessToken(forceRefresh: true);
            $result = $this->performUpload($fileContents, $apiArg, $accessToken, $fullDropboxPath);
        }

        if ($result['curl_error'] !== null) {
            Log::error('Dropbox upload failed: curl error', ['error' => $result['curl_error']]);
            throw new RuntimeException('Dropbox upload failed: '.$result['curl_error']);
        }

        $response = $result['response'];
        $httpCode = $result['http_code'];
        $data = $this->decodeJsonObject($response);

        if ($httpCode !== 200) {
            $errorSummary = $this->arrayString($data, 'error_summary', 'Unknown error');
            Log::error('Dropbox upload failed: API error', [
                'http_code' => $httpCode,
                'error_summary' => $errorSummary,
                'response' => $response,
            ]);
            throw new RuntimeException(sprintf('Dropbox upload failed (%s): %s', $httpCode, $errorSummary));
        }

        Log::info('Dropbox upload succeeded', [
            'dropbox_path' => $this->arrayString($data, 'path_display', $fullDropboxPath),
            'file_id' => $this->arrayString($data, 'id'),
            'size' => $data['size'] ?? null,
        ]);

        return ['success' => true, 'data' => $data, 'error' => null];
    }

    /** @return array<string, mixed> */
    private function decodeJsonObject(?string $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed> $data */
    private function arrayString(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return array{response: string|null, http_code: int, curl_error: string|null} */
    private function performUpload(string $fileContents, string $apiArg, string $accessToken, string $dropboxPath): array
    {
        Log::debug('Sending Dropbox upload request.', ['dropbox_path' => $dropboxPath]);

        if ($this->uploadExecutor instanceof Closure) {
            return $this->normalizeUploadResult(($this->uploadExecutor)($fileContents, $apiArg, $accessToken, $dropboxPath));
        }

        $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$accessToken,
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

        if ($response === false || ! is_string($response)) {
            Log::error('Dropbox upload curl request failed.', [
                'dropbox_path' => $dropboxPath,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
            ]);

            return [
                'response' => null,
                'http_code' => $httpCode,
                'curl_error' => $curlError,
            ];
        }

        Log::debug('Dropbox upload request completed.', [
            'dropbox_path' => $dropboxPath,
            'http_code' => $httpCode,
        ]);

        return [
            'response' => $response,
            'http_code' => $httpCode,
            'curl_error' => null,
        ];
    }

    /** @return array{response: string|null, http_code: int, curl_error: string|null} */
    private function normalizeUploadResult(mixed $result): array
    {
        if (! is_array($result)) {
            return [
                'response' => null,
                'http_code' => 0,
                'curl_error' => 'Invalid upload executor result.',
            ];
        }

        $response = $result['response'] ?? null;
        $httpCode = $result['http_code'] ?? 0;
        $curlError = $result['curl_error'] ?? null;

        return [
            'response' => is_string($response) ? $response : null,
            'http_code' => is_int($httpCode) ? $httpCode : 0,
            'curl_error' => is_string($curlError) ? $curlError : null,
        ];
    }
}
