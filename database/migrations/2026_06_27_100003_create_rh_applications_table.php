<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_applications')) {
            return;
        }

        Schema::create('rh_applications', function (Blueprint $table) {
            $table->id();

            // Applicant — app-level FK (students is MyISAM)
            $table->unsignedInteger('student_id')->index();
            $table->unsignedBigInteger('school_year_id')->index();

            // Application details (from F-RHU-01)
            $table->string('grade_level', 10)->nullable();
            $table->string('scholarship_category', 50)->nullable();
            $table->string('home_province')->nullable();
            $table->unsignedSmallInteger('estimated_distance_km')->nullable();
            $table->enum('preferred_hall', ['BRH', 'GRH'])->nullable();

            // Foster parent info (REQUIRED on the form)
            $table->string('foster_parent_name')->nullable();
            $table->string('foster_parent_contact', 50)->nullable();
            $table->string('foster_parent_address')->nullable();

            // Evaluation workflow (SSM 5.1)
            $table->enum('status', ['pending', 'evaluated', 'approved', 'rejected', 'waitlisted'])
                  ->default('pending')->index();
            $table->text('evaluation_notes')->nullable();
            $table->unsignedBigInteger('evaluated_by')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_applications');
    }
};
