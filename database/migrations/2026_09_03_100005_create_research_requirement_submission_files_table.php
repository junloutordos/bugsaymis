<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_requirement_submission_files', function (Blueprint $table) {
            $table->id();
            // Explicit short FK name — the default auto-generated name exceeds
            // MySQL's 64-character identifier limit (see the submissions
            // migration for the same fix).
            $table->foreignId('research_requirement_submission_id')
                ->constrained(table: 'research_requirement_submissions', indexName: 'fk_rr_submission_files_submission')
                ->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('s3_key', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirement_submission_files');
    }
};
