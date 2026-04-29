<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Override;
use RuntimeException;
use Throwable;

final class RewrapDropboxTokens extends Command
{
    #[Override]
    protected $signature = 'dropbox:rewrap-tokens
        {--from-key= : Previous APP_KEY (e.g. base64:...) used to decrypt existing token values}
        {--force : Skip confirmation prompt}';

    #[Override]
    protected $description = 'Re-encrypt Dropbox token values with the current APP_KEY (useful after APP_KEY rotation)';

    public function handle(): int
    {
        $fromKeyOption = $this->option('from-key');

        Log::info('Starting Dropbox token rewrap command.', [
            'force' => (bool) $this->option('force'),
            'from_key_provided' => is_string($fromKeyOption) && $fromKeyOption !== '',
        ]);

        if (! is_string($fromKeyOption) || $fromKeyOption === '') {
            Log::error('Dropbox token rewrap command missing required --from-key option.');
            $this->error('Missing required option: --from-key=<previous APP_KEY>');

            return self::FAILURE;
        }

        if (! Schema::hasTable('dropbox_tokens')) {
            $this->warn('dropbox_tokens table does not exist. Nothing to rewrap.');

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force')) {
            $confirmed = $this->confirm('Re-encrypt Dropbox token values using the current APP_KEY?', true);

            if (! $confirmed) {
                $this->line('Canceled.');
                Log::info('Dropbox token rewrap command canceled by user.');

                return self::SUCCESS;
            }
        }

        $rows = DB::table('dropbox_tokens')->orderBy('id')->get(['id', 'access_token', 'refresh_token']);

        if ($rows->isEmpty()) {
            $this->line('No rows found in dropbox_tokens.');
            Log::info('Dropbox token rewrap found no rows to process.');

            return self::SUCCESS;
        }

        $oldEncrypter = $this->buildEncrypterFromAppKey($fromKeyOption);
        $rewrappedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $accessTokenValue = is_string($row->access_token) ? $row->access_token : '';
                $refreshTokenValue = is_string($row->refresh_token) ? $row->refresh_token : '';
                $accessToken = $oldEncrypter->decryptString($accessTokenValue);
                $refreshToken = $oldEncrypter->decryptString($refreshTokenValue);

                DB::table('dropbox_tokens')
                    ->where('id', $row->id)
                    ->update([
                        'access_token' => Crypt::encryptString($accessToken),
                        'refresh_token' => Crypt::encryptString($refreshToken),
                    ]);

                $rewrappedCount++;
            }

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();

            Log::error('Dropbox token rewrap failed.', [
                'message' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);

            $this->error('Dropbox token rewrap failed: '.$throwable->getMessage());

            return self::FAILURE;
        }

        Log::info('Dropbox token rewrap completed.', ['rows_rewrapped' => $rewrappedCount]);

        $this->info('Re-encrypted '.$rewrappedCount.' row(s) in dropbox_tokens with the current APP_KEY.');

        return self::SUCCESS;
    }

    private function buildEncrypterFromAppKey(string $appKey): Encrypter
    {
        $cipherConfig = config('app.cipher', 'AES-256-CBC');
        $cipher = is_string($cipherConfig) && $cipherConfig !== '' ? $cipherConfig : 'AES-256-CBC';
        $decodedKey = str_starts_with($appKey, 'base64:') ? base64_decode(mb_substr($appKey, 7), true) : $appKey;

        throw_if(! is_string($decodedKey) || $decodedKey === '', RuntimeException::class, 'Unable to decode --from-key.');

        throw_unless(Encrypter::supported($decodedKey, $cipher), RuntimeException::class, 'The provided --from-key is not valid for cipher '.$cipher.'.');

        return new Encrypter($decodedKey, $cipher);
    }
}
