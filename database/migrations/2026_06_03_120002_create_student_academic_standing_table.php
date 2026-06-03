<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_standing', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM)');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();

            // GWA = average of all final_ge values across subjects (GE scale 1.0–5.0)
            $table->decimal('gwa', 5, 3)->nullable()
                  ->comment('Simple average of final_ge across all subjects (GE scale; lower is better)');

            $table->unsignedTinyInteger('subject_count')->default(0)
                  ->comment('Total subjects enrolled for this school year');
            $table->unsignedTinyInteger('failed_subject_count')->default(0)
                  ->comment('Subjects where final_ge > 3.0');

            $table->enum('honors', ['With Highest Honors', 'With High Honors', 'With Honors', 'None'])
                  ->default('None');

            $table->enum('standing', ['Promoted', 'Retained', 'Excluded', 'Transferred', 'Graduated', 'Dropped'])
                  ->default('Promoted');

            $table->text('notes')->nullable();

            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->unique(['student_id', 'school_year_id'], 'uq_academic_standing');
            $table->index(['school_year_id', 'standing']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_standing');
    }
};
