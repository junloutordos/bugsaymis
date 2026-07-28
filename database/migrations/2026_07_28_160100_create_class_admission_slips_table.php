<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_admission_slips', function (Blueprint $table) {
            $table->id();
            $table->integer('student_id')->comment('FK to students.id (legacy INT pk); no constraint');
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->date('infraction_date');
            $table->enum('infraction_type', ['absence', 'tardy', 'cutting']);
            $table->enum('excused_status', ['excused', 'unexcused']);
            $table->string('reason')->nullable();
            $table->string('supporting_document_path')->nullable()->comment('S3 key');
            $table->foreignId('issued_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['student_id', 'infraction_date'], 'admission_slip_student_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_admission_slips');
    }
};
