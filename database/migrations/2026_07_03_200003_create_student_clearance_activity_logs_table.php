<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_clearance_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_clearance_id');
            $table->foreignId('student_clearance_item_id')->nullable();
            $table->string('actor_type', 30)->default('user');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 60);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('student_clearance_id', 'sc_logs_clearance_fk')
                ->references('id')
                ->on('student_clearances')
                ->cascadeOnDelete();
            $table->foreign('student_clearance_item_id', 'sc_logs_item_fk')
                ->references('id')
                ->on('student_clearance_items')
                ->cascadeOnDelete();
            $table->index(['student_clearance_id', 'created_at'], 'idx_clearance_log_clearance_date');
            $table->index(['student_clearance_item_id', 'created_at'], 'idx_clearance_log_item_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_clearance_activity_logs');
    }
};
