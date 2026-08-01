<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_service_record_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('service_from');
            $table->date('service_to')->nullable();
            $table->date('appointment_issued_at')->nullable();
            $table->date('assumed_at')->nullable();
            $table->string('position_title');
            $table->string('employment_status', 40)->nullable();
            $table->string('appointment_nature', 40)->nullable();
            $table->string('agency_name')->nullable();
            $table->string('station_place')->nullable();
            $table->string('branch_division')->nullable();
            $table->string('plantilla_item_no', 80)->nullable();
            $table->decimal('salary_amount', 14, 2)->nullable();
            $table->string('salary_basis', 20)->nullable();
            $table->unsignedTinyInteger('salary_grade')->nullable();
            $table->unsignedTinyInteger('salary_step')->nullable();
            $table->string('csc_action', 40)->nullable();
            $table->date('csc_action_at')->nullable();
            $table->boolean('credited_as_government_service')->default(true);
            $table->string('source_type', 30)->default('manual');
            $table->string('status', 20)->default('draft');
            $table->date('separation_date')->nullable();
            $table->string('separation_cause')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'service_from', 'service_to'], 'hr_sr_entry_period_idx');
            $table->index(['credited_as_government_service', 'status'], 'hr_sr_entry_credit_idx');
        });

        Schema::create('hr_service_record_lwop_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_record_entry_id')
                ->constrained('hr_service_record_entries')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->decimal('days', 8, 3)->nullable();
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['service_record_entry_id', 'date_from'], 'hr_sr_lwop_period_idx');
        });

        Schema::create('hr_service_record_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_record_entry_id')
                ->constrained('hr_service_record_entries')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('title');
            $table->string('s3_path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_record_entry_id', 'document_type'], 'hr_sr_evidence_type_idx');
        });

        Schema::create('hr_service_record_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_record_entry_id')->nullable()
                ->constrained('hr_service_record_entries')->nullOnDelete();
            $table->string('action_type', 40);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('office_order_no')->nullable();
            $table->string('station_place')->nullable();
            $table->string('branch_division')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'effective_from'], 'hr_sr_action_period_idx');
        });

        Schema::create('hr_service_record_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->string('content_hash', 64);
            $table->foreignId('document_request_id')->nullable()
                ->constrained('hr_document_requests')->nullOnDelete();
            $table->foreignId('issued_document_id')->nullable()
                ->constrained('hr_issued_documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'version']);
            $table->unique('issued_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_service_record_versions');
        Schema::dropIfExists('hr_service_record_actions');
        Schema::dropIfExists('hr_service_record_evidence');
        Schema::dropIfExists('hr_service_record_lwop_periods');
        Schema::dropIfExists('hr_service_record_entries');
    }
};
