<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->text('field_hashes')->nullable();
        });

        Schema::create('participant_pdf_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('participant_id');
            $table->unsignedInteger('version');
            $table->string('neon_data_hash', 64);
            $table->uuid('job_uuid')->nullable();
            $table->json('changed_fields');
            $table->string('filename');
            $table->timestamps();
            $table->unique(['participant_id', 'version']);
            $table->foreign('participant_id')->references('id')->on('participants');
        });

        Schema::drop('neon_participant_hashes');
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_pdf_versions');
        Schema::dropIfExists('participants');

        Schema::create('neon_participant_hashes', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->timestamps();
        });
    }
};
