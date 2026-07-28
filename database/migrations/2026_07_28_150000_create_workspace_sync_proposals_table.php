<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_sync_proposals', function (Blueprint $table) {
            $table->id();
            $table->enum('subject_type', ['student', 'employee']);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->string('current_email')->nullable();
            $table->string('proposed_email');
            $table->string('org_unit_path')->nullable();
            $table->enum('confidence', ['high', 'medium']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id'], 'workspace_sync_proposals_subject_unique');
            $table->index(['subject_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_sync_proposals');
    }
};
