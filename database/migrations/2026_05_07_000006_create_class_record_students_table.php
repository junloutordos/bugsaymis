<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_quarter_id')->constrained('class_record_quarters')->cascadeOnDelete();
            // No FK constraint — students referenced via legacy section_students.studentid (integer, no FK)
            $table->unsignedInteger('student_id')->nullable()->comment('FK → section_students.studentid (no constraint; legacy int)');
            $table->string('family_name');
            $table->string('given_name');
            $table->string('middle_initial', 5)->nullable();
            $table->enum('sex', ['M', 'F']);
            $table->unsignedTinyInteger('sequence_number');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['class_record_quarter_id', 'sequence_number'], 'crstud_quarter_seq_idx');
            $table->index(['class_record_quarter_id', 'student_id'], 'crstud_quarter_student_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_students');
    }
};
