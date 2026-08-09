<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_assignment_id')->constrained('learn_assignments')->cascadeOnDelete();
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');
            $table->longText('text_body')->nullable();
            $table->foreignId('learn_file_id')->nullable()->constrained('learn_files')->nullOnDelete();
            $table->string('link_url')->nullable();
            $table->timestamp('submitted_at');
            $table->decimal('score', 6, 2)->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learn_assignment_id', 'student_id'], 'learn_submissions_assignment_student_unique');
            $table->index(['student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_submissions');
    }
};
