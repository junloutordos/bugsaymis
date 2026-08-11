<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issuance_recipients', function (Blueprint $table) {
            $table->unsignedInteger('student_id')->nullable()->after('user_id');
            $table->index(['issuance_id', 'student_id']);
        });

        Schema::create('issuance_recipient_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuance_id')->constrained()->onDelete('cascade');
            $table->string('type', 20); // all_staff | office | division | individual_staff | all_students | section | grade_level | individual_student
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->index('issuance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issuance_recipient_criteria');

        Schema::table('issuance_recipients', function (Blueprint $table) {
            $table->dropIndex(['issuance_recipients_issuance_id_student_id_index']);
            $table->dropColumn('student_id');
        });
    }
};
