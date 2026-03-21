<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('job_vacancy_id')->constrained('job_vacancies')->restrictOnDelete();
            $table->foreignId('recruitment_type_id')->constrained('recruitment_types')->restrictOnDelete();
            $table->date('application_date');
            $table->string('current_stage')->default('submitted');
            // stages: submitted, screening, exam, interview, ranking, selection, placement, rejected, withdrawn
            $table->boolean('is_internal')->default(false); // internal (existing employee) vs external
            $table->decimal('priority_score', 8, 4)->nullable(); // computed weighted score
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['applicant_id', 'job_vacancy_id']); // one application per vacancy
            $table->index(['job_vacancy_id', 'current_stage']);
            $table->index('recruitment_type_id');
            $table->index('current_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
