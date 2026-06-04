<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 20)->unique();

            $table->foreignId('school_year_id')->constrained('school_years');
            $table->unsignedTinyInteger('grade_level')->default(7);

            // Personal info
            $table->string('lastname', 100);
            $table->string('firstname', 100);
            $table->string('middlename', 100)->nullable();
            $table->string('birthday', 20)->nullable();
            $table->string('sex', 10)->nullable();
            $table->string('birth_place', 200)->nullable();
            $table->string('lrn', 30)->nullable();

            // Contact & address
            $table->text('address')->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->string('email', 150)->nullable();

            // Father info
            $table->string('father_name', 200)->nullable();
            $table->string('father_occupation', 150)->nullable();
            $table->string('father_contact', 20)->nullable();
            $table->string('father_email', 150)->nullable();

            // Mother info
            $table->string('mother_name', 200)->nullable();
            $table->string('mother_occupation', 150)->nullable();
            $table->string('mother_contact', 20)->nullable();
            $table->string('mother_email', 150)->nullable();

            // Guardian (if neither parent)
            $table->string('guardian_name', 200)->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->string('guardian_contact', 20)->nullable();
            $table->string('guardian_email', 150)->nullable();

            // Previous school
            $table->string('previous_school', 300)->nullable();
            $table->string('previous_school_address', 300)->nullable();
            $table->string('grade_level_completed', 50)->nullable();
            $table->string('school_year_completed', 30)->nullable();

            // Registrar workflow
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected'])
                  ->default('pending');
            $table->string('pisays_id_assigned', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Link to created student record on approval
            $table->unsignedInteger('student_id')->nullable();

            $table->timestamps();

            $table->index(['school_year_id', 'status']);
            $table->index('grade_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_applications');
    }
};
