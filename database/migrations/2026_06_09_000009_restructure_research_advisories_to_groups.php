<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_advisory_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_advisory_id')->constrained('research_advisories')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->nullable()->comment('students.id reference');
            $table->string('student_name', 200);
            $table->timestamps();
        });

        Schema::table('research_advisories', function (Blueprint $table) {
            $table->dropColumn(['student_name', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_advisory_members');

        Schema::table('research_advisories', function (Blueprint $table) {
            $table->string('student_name', 200)->nullable()->after('academic_term_id');
            $table->unsignedInteger('student_id')->nullable()->after('student_name');
        });
    }
};
