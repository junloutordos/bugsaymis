<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_document_request_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->string('template_key', 80);
            $table->boolean('requires_ocd_approval')->default(false);
            $table->string('signatory', 20)->default('hr');
            $table->unsignedSmallInteger('sla_business_days')->default(3);
            $table->boolean('supports_digital')->default(true);
            $table->boolean('supports_pickup')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hr_document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->nullable()->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('request_type_id')->constrained('hr_document_request_types');
            $table->foreignId('processor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ocd_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('submitted')->index();
            $table->text('purpose');
            $table->string('addressed_to')->nullable();
            $table->string('destination_agency')->nullable();
            $table->date('needed_at')->nullable();
            $table->unsignedTinyInteger('copies')->default(1);
            $table->string('delivery_method', 20)->default('digital');
            $table->text('special_instructions')->nullable();
            $table->text('employee_visible_remarks')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('document_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('routed_to_ocd_at')->nullable();
            $table->timestamp('ocd_approved_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'created_at']);
            $table->index(['processor_id', 'status']);
        });

        Schema::create('hr_document_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('hr_document_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('hr_issued_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('hr_document_requests')->cascadeOnDelete();
            $table->string('control_number', 40)->unique();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('s3_path');
            $table->string('content_hash', 64);
            $table->uuid('qr_token')->unique();
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['request_id', 'version']);
        });

        Schema::create('hr_document_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('hr_document_requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('s3_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_document_request_attachments');
        Schema::dropIfExists('hr_issued_documents');
        Schema::dropIfExists('hr_document_request_events');
        Schema::dropIfExists('hr_document_requests');
        Schema::dropIfExists('hr_document_request_types');
    }
};
