<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedule_approval_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms');
            $table->string('status', 30)->default('pending_ocd')->index();
            $table->longText('schedule_snapshot');
            $table->char('schedule_hash', 64);
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users');
            $table->timestamp('returned_at')->nullable();
            $table->text('return_remarks')->nullable();
            $table->timestamps();

            $table->index(['academic_term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_approval_batches');
    }
};
