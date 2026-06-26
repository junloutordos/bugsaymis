<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipline_cases')) {
            return;
        }

        Schema::create('discipline_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_no')->unique();                       // DISC-YYYY-MM-NNN

            // Respondent (student) — app-level FK only (students is MyISAM)
            $table->unsignedInteger('student_id')->index();

            // Filer — any faculty/staff (users.id), with position/section captured at filing
            $table->unsignedBigInteger('filer_id')->index();
            $table->string('filer_position_snapshot')->nullable();
            $table->string('filer_section_snapshot')->nullable();      // respondent's yr-sec at filing

            // Offense classification
            $table->unsignedBigInteger('offense_id')->nullable()->index();
            $table->unsignedTinyInteger('offense_level')->nullable();
            $table->string('nature_of_offense')->nullable();           // free-text "consult Code of Conduct"

            // Incident details (Anecdotal Report)
            $table->date('incident_date')->nullable();
            $table->string('incident_time')->nullable();
            $table->string('place')->nullable();
            $table->text('other_witnesses')->nullable();
            $table->longText('narrative')->nullable();
            $table->text('attachments_note')->nullable();              // enumerated attachments
            $table->longText('interventions_done')->nullable();        // interventions stated on the form

            // Workflow
            $table->string('status')->default('filed')->index();       // filed|received|under_review|resolved|dismissed|cancelled
            $table->date('date_filed')->nullable();
            $table->string('time_filed')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->longText('resolution')->nullable();
            $table->longText('sanction')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();

            // Bullying handling
            $table->boolean('is_bullying')->default(false);
            $table->string('threat_level')->nullable();                // low|medium|high
            $table->timestamp('parent_notified_at')->nullable();
            $table->timestamp('respond_by')->nullable();               // 24h SLA for high-threat

            $table->unsignedBigInteger('school_year_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_cases');
    }
};
