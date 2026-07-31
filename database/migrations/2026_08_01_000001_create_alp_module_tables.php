<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alp_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->text('nature')->nullable();
            $table->text('mission')->nullable();
            $table->json('objectives')->nullable();
            $table->string('logo_file_id')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('alp_program_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_id')->constrained('alp_programs')->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->foreignId('adviser_assignment_id')->nullable()->constrained('load_assignments')->nullOnDelete();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cycle_type', 30)->default('accreditation');
            $table->string('status', 40)->default('draft')->index();
            $table->json('application_data')->nullable();
            $table->text('deficiency_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('recommended_at')->nullable();
            $table->timestamp('accredited_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['alp_program_id', 'school_year_id'], 'alp_cycle_program_sy_unique');
        });

        Schema::create('alp_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->string('status', 30)->default('active')->index();
            $table->string('consent_status', 30)->default('pending')->index();
            $table->string('consent_file_id')->nullable();
            $table->string('completion_certificate_file_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->date('joined_at')->nullable();
            $table->text('accountability')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_year_id', 'student_id'], 'alp_membership_student_sy_unique');
            $table->index(['alp_program_cycle_id', 'status']);
        });

        Schema::create('alp_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->foreignId('alp_membership_id')->constrained('alp_memberships')->cascadeOnDelete();
            $table->string('position', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('registrar_status', 30)->default('pending')->index();
            $table->json('academic_standing_snapshot')->nullable();
            $table->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->text('certification_remarks')->nullable();
            $table->timestamps();
            $table->unique(['alp_program_cycle_id', 'alp_membership_id'], 'alp_officer_member_unique');
        });

        Schema::create('alp_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->string('form_code', 80)->nullable();
            $table->unsignedSmallInteger('version_no')->default(2);
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->json('content')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('file_id')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['alp_program_cycle_id', 'document_type'], 'alp_document_cycle_type_unique');
        });

        Schema::create('alp_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->foreignId('ams_activity_id')->nullable()->constrained('ams_activities')->nullOnDelete();
            $table->string('title');
            $table->string('activity_type', 30)->default('regular')->index();
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->date('end_date');
            $table->time('end_time')->nullable();
            $table->text('learning_outcomes')->nullable();
            $table->text('target_participants')->nullable();
            $table->string('venue')->nullable();
            $table->boolean('is_off_campus')->default(false);
            $table->decimal('budget_amount', 14, 2)->default(0);
            $table->text('resources_needed')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('risk_level', 20)->nullable();
            $table->json('risk_data')->nullable();
            $table->boolean('consent_required')->default(true);
            $table->text('completion_highlights')->nullable();
            $table->text('accomplishments')->nullable();
            $table->text('recommendations')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['alp_program_cycle_id', 'start_date']);
        });

        Schema::create('alp_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->foreignId('alp_activity_id')->nullable()->constrained('alp_activities')->nullOnDelete();
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('topic')->nullable();
            $table->string('venue')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('alp_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_session_id')->constrained('alp_sessions')->cascadeOnDelete();
            $table->foreignId('alp_membership_id')->constrained('alp_memberships')->cascadeOnDelete();
            $table->string('status', 20)->default('present');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['alp_session_id', 'alp_membership_id'], 'alp_attendance_session_member_unique');
        });

        Schema::create('alp_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->foreignId('alp_activity_id')->nullable()->constrained('alp_activities')->nullOnDelete();
            $table->date('transaction_date');
            $table->string('entry_type', 30)->index();
            $table->string('category', 80)->nullable();
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('source')->nullable();
            $table->string('receipt_file_id')->nullable();
            $table->string('status', 20)->default('posted');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('alp_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->string('report_type', 40);
            $table->string('period', 40);
            $table->json('data')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('pdf_file_id')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['alp_program_cycle_id', 'report_type', 'period'], 'alp_report_cycle_type_period_unique');
        });

        Schema::create('alp_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alp_program_cycle_id')->constrained('alp_program_cycles')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alp_activity_logs');
        Schema::dropIfExists('alp_reports');
        Schema::dropIfExists('alp_financial_entries');
        Schema::dropIfExists('alp_attendance');
        Schema::dropIfExists('alp_sessions');
        Schema::dropIfExists('alp_activities');
        Schema::dropIfExists('alp_documents');
        Schema::dropIfExists('alp_officers');
        Schema::dropIfExists('alp_memberships');
        Schema::dropIfExists('alp_program_cycles');
        Schema::dropIfExists('alp_programs');
    }
};
