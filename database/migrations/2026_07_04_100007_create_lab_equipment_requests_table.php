<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratory Request and Equipment Accountability Form (PSHS-00-F-CID-20).
 * Header + line items (separate table). Tracks issue -> return -> inspection,
 * flagging equipment for repair/replacement on damaged return.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 40)->unique();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('lab_reservations')->nullOnDelete();
            $table->string('grade_level_section', 100)->nullable();
            $table->unsignedSmallInteger('number_of_students')->nullable();
            $table->string('subject', 150)->nullable();
            $table->string('concurrent_topic', 200)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('teacher_in_charge', 150)->nullable();
            $table->string('venue', 150)->nullable();
            $table->date('date_start');
            $table->date('date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name', 150)->nullable();
            $table->string('requester_type', 20)->default('teacher');
            $table->json('student_group')->nullable();
            $table->foreignId('endorsed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('endorsed_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by_id')->nullable()->constrained('users')->nullOnDelete();       // SRA releasing
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('returned_received_by_id')->nullable()->constrained('users')->nullOnDelete(); // SRA inspecting on return
            $table->timestamp('returned_at')->nullable();
            // pending | endorsed | approved | issued | returned | completed | declined | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->text('decline_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_equipment_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_equipment_request_id')->constrained('lab_equipment_requests')->cascadeOnDelete();
            $table->foreignId('lab_equipment_id')->nullable()->constrained('lab_equipment')->nullOnDelete();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('item');
            $table->string('description')->nullable();
            $table->string('issued_condition', 255)->nullable();
            $table->string('returned_condition', 255)->nullable();
            $table->boolean('needs_repair')->default(false);
            $table->boolean('needs_replacement')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_equipment_request_items');
        Schema::dropIfExists('lab_equipment_requests');
    }
};
