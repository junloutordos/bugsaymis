<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issuances', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('type', 20);                        // SO, TO, MEMO, OO, AO, CIRC, NOTICE
            $table->string('control_number', 30)->unique();    // e.g. SO-2026-001
            $table->unsignedSmallInteger('series_no');         // auto-incremented per type per year
            $table->unsignedSmallInteger('year');

            // Content
            $table->string('title');
            $table->longText('content')->nullable();           // HTML body (editor mode)
            $table->string('attachment_path')->nullable();     // S3 path (upload mode)
            $table->string('attachment_filename')->nullable(); // original filename
            $table->string('attachment_mime')->nullable();

            // Recipients
            $table->string('recipient_type', 20)->default('all'); // all | office | individual

            // Status
            $table->string('status', 20)->default('draft');    // draft | released | cancelled

            // Signing & verification
            $table->string('content_hash', 64)->nullable();    // sha256 for tamper detection
            $table->uuid('qr_token')->unique();                // public verification token
            $table->timestamp('released_at')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');

            $table->timestamps();

            $table->index(['type', 'year', 'series_no']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuances');
    }
};
