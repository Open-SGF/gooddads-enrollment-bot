<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DropboxToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class ResetDropboxAuth extends Command
{
    protected $signature = 'dropbox:reset-auth
        {--with-sessions : Also clear the sessions table}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Clear stored Dropbox OAuth tokens to force a clean re-authorization flow';

    public function handle(): int
    {
        Log::info('Starting Dropbox auth reset command.', [
            'with_sessions' => (bool) $this->option('with-sessions'),
            'force' => (bool) $this->option('force'),
        ]);

        if (! (bool) $this->option('force')) {
            $confirmed = $this->confirm('Delete stored Dropbox OAuth tokens and reset local auth state?', true);

            if (! $confirmed) {
                $this->line('Canceled.');
                Log::info('Dropbox auth reset command canceled by user.');

                return self::SUCCESS;
            }
        }

        $deletedTokens = DropboxToken::query()->delete();

        if ($deletedTokens > 0) {
            $this->info('Deleted '.$deletedTokens.' row(s) from dropbox_tokens.');
            Log::info('Dropbox auth reset deleted token rows.', ['deleted_tokens' => $deletedTokens]);
        } else {
            $this->line('No rows found in dropbox_tokens.');
            Log::info('Dropbox auth reset found no token rows to delete.');
        }

        if ((bool) $this->option('with-sessions')) {
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->truncate();
                $this->info('Cleared sessions table.');
                Log::info('Dropbox auth reset cleared sessions table.');
            } else {
                $this->warn('Sessions table does not exist. Skipped session clearing.');
                Log::warning('Dropbox auth reset skipped session clearing because sessions table does not exist.');
            }
        }

        $this->line('Next step: visit /dropbox/authorize to connect the account you want to test.');
        Log::info('Dropbox auth reset command completed.');

        return self::SUCCESS;
    }
}