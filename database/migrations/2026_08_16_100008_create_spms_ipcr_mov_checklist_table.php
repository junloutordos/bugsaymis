<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_ipcr_mov_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spms_ipcr_target_id')->constrained('spms_ipcr_targets')->cascadeOnDelete();
            $table->string('document_type'); // e.g. SIP, OCM/CFFS, Grading Sheets, APR
            $table->string('status')->default('pending'); // pending | submitted | not_applicable
            $table->string('s3_key')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_ipcr_mov_checklist');
    }
};
