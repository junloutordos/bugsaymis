<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_discussion_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_discussion_id')->constrained('learn_discussions')->cascadeOnDelete();
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');
            $table->decimal('points_earned', 6, 2)->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learn_discussion_id', 'student_id'], 'ldg_discussion_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_discussion_grades');
    }
};
