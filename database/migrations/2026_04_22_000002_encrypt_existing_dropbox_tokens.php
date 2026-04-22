<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dropbox_tokens')) {
            return;
        }

        DB::table('dropbox_tokens')->orderBy('id')->eachById(function (object $row): void {
            $encryptedAccessToken = $this->encryptIfPlaintext($row->access_token);
            $encryptedRefreshToken = $this->encryptIfPlaintext($row->refresh_token);

            DB::table('dropbox_tokens')
                ->where('id', $row->id)
                ->update([
                    'access_token' => $encryptedAccessToken,
                    'refresh_token' => $encryptedRefreshToken,
                ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dropbox_tokens')) {
            return;
        }

        DB::table('dropbox_tokens')->orderBy('id')->eachById(function (object $row): void {
            $decryptedAccessToken = $this->decryptIfEncrypted($row->access_token);
            $decryptedRefreshToken = $this->decryptIfEncrypted($row->refresh_token);

            DB::table('dropbox_tokens')
                ->where('id', $row->id)
                ->update([
                    'access_token' => $decryptedAccessToken,
                    'refresh_token' => $decryptedRefreshToken,
                ]);
        });
    }

    private function encryptIfPlaintext(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            Crypt::decryptString($value);

            return $value;
        } catch (DecryptException) {
            return Crypt::encryptString($value);
        }
    }

    private function decryptIfEncrypted(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
};