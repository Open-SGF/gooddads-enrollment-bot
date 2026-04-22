<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dropbox_tokens', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->dateTime('expires_at');
            $table->string('token_type')->default('bearer');
            $table->text('scope')->nullable();
            $table->string('account_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropbox_tokens');
    }
};