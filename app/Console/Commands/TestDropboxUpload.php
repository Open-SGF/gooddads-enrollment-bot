<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DropboxToken;
use App\Services\DropboxUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Override;
use Throwable;

final class TestDropboxUpload extends Command
{
    #[Override]
    protected $signature = 'dropbox:test-upload
        {--remote= : Dropbox destination path relative to DROPBOX_UPLOAD_PATH}
        {--expire-token : Force the stored token to expire before uploading}';

    #[Override]
    protected $description = 'Upload a small probe file to Dropbox and optionally force the token refresh path';

    public function __construct(
        private readonly DropboxUploadService $dropboxUploadService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $localRelativePath = null;

        Log::info('Starting Dropbox upload validation command.', [
            'expire_token' => (bool) $this->option('expire-token'),
            'remote' => $this->option('remote'),
        ]);

        try {
            if ((bool) $this->option('expire-token')) {
                $storedToken = DropboxToken::query()->find(1);

                if ($storedToken === null) {
                    Log::error('Dropbox upload validation command cannot force expiration because no token is stored.');
                    $this->error('No Dropbox token is stored yet. Complete the OAuth flow first.');

                    return self::FAILURE;
                }

                $storedToken->forceFill([
                    'expires_at' => Date::now()->subMinutes(5),
                ])->save();

                $this->warn('Forced the stored Dropbox token to expire before upload.');
                Log::info('Forced Dropbox token expiration for upload validation command.');
            }

            $timestamp = Date::now()->format('Y-m-d_H-i-s');
            $localRelativePath = 'dropbox-test/upload_probe_'.$timestamp.'.txt';
            $remotePath = $this->option('remote');

            if (! is_string($remotePath) || $remotePath === '') {
                $remotePath = 'dropbox-test/'.Str::uuid()->toString().'_upload_probe.txt';
            }

            $contents = implode(PHP_EOL, [
                'Dropbox upload validation probe',
                'Generated at: '.Date::now()->toIso8601String(),
                'Remote path: '.$remotePath,
            ]);

            Storage::put($localRelativePath, $contents);

            $absoluteLocalPath = Storage::path($localRelativePath);

            $result = $this->dropboxUploadService->upload($absoluteLocalPath, $remotePath);
            $resultData = is_array($result['data']) ? $result['data'] : [];
            $uploadedPath = is_string($resultData['path_display'] ?? null) ? $resultData['path_display'] : $remotePath;

            $this->info('Dropbox upload succeeded.');
            $this->line('Local file: '.$absoluteLocalPath);
            $this->line('Remote path: '.$uploadedPath);

            Log::info('Dropbox upload validation command succeeded.', [
                'local_path' => $absoluteLocalPath,
                'remote_path' => $uploadedPath,
            ]);

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error('Dropbox upload test failed: '.$throwable->getMessage());

            Log::error('Dropbox upload validation command failed.', [
                'message' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);

            return self::FAILURE;
        } finally {
            if (is_string($localRelativePath)) {
                Storage::delete($localRelativePath);
                Log::debug('Cleaned up local Dropbox upload validation probe file.', [
                    'local_relative_path' => $localRelativePath,
                ]);
            }
        }
    }
}
