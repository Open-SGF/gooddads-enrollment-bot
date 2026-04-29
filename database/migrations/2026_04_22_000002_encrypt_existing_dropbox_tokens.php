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
            $accessToken = is_string($row->access_token) ? $row->access_token : null;
            $refreshToken = is_string($row->refresh_token) ? $row->refresh_token : null;
            $encryptedAccessToken = $this->encryptIfPlaintext($accessToken);
            $encryptedRefreshToken = $this->encryptIfPlaintext($refreshToken);

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
            $accessToken = is_string($row->access_token) ? $row->access_token : null;
            $refreshToken = is_string($row->refresh_token) ? $row->refresh_token : null;
            $decryptedAccessToken = $this->decryptIfEncrypted($accessToken);
            $decryptedRefreshToken = $this->decryptIfEncrypted($refreshToken);

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
            if ($this->looksLikeLaravelEncryptedPayload($value)) {
                return $value;
            }

            return Crypt::encryptString($value);
        }
    }

    private function looksLikeLaravelEncryptedPayload(string $value): bool
    {
        $decoded = base64_decode($value, true);

        if (! is_string($decoded) || $decoded === '') {
            return false;
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload)) {
            return false;
        }

        foreach (['iv', 'value', 'mac'] as $requiredKey) {
            if (! array_key_exists($requiredKey, $payload) || ! is_string($payload[$requiredKey]) || $payload[$requiredKey] === '') {
                return false;
            }
        }

        return ! (array_key_exists('tag', $payload) && ! is_string($payload['tag']));
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
