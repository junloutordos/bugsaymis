<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_clearance_id')->constrained('student_clearances')->cascadeOnDelete();
            $table->string('requirement_code', 80);
            $table->string('requirement_label');
            $table->string('requirement_type', 30);
            $table->string('requirement_group', 30)->default('administrative');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('class_record_id')->nullable();
            $table->unsignedBigInteger('load_assignment_id')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_permission', 100)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->text('accountability')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['student_clearance_id', 'requirement_code'], 'uq_clearance_item_code');
            $table->index(['assigned_user_id', 'status'], 'idx_clearance_item_assigned_status');
            $table->index(['assigned_permission', 'status'], 'idx_clearance_item_permission_status');
            $table->index(['requirement_type', 'status'], 'idx_clearance_item_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_clearance_items');
    }
};
