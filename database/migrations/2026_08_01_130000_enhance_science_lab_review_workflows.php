<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_request_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('requester_student_id')->nullable()->index();
            $table->string('requester_name', 150);
            $table->string('requester_type', 20)->default('teacher');
            $table->foreignId('assigned_endorser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending_endorsement')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('csm_completed_at')->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('lab_reservations', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('id')->constrained('lab_request_bundles')->nullOnDelete();
        });
        Schema::table('lab_equipment_requests', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('id')->constrained('lab_request_bundles')->nullOnDelete();
            $table->string('received_by_name', 150)->nullable()->after('issued_at');
        });
        Schema::table('lab_reagent_requests', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('id')->constrained('lab_request_bundles')->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->after('school_year_id')->constrained('lab_reservations')->nullOnDelete();
            $table->string('received_by_name', 150)->nullable()->after('released_at');
            $table->timestamp('received_at')->nullable()->after('received_by_name');
        });

        Schema::create('lab_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('lab_request_bundles')->cascadeOnDelete();
            $table->string('form_type', 20)->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('actor_student_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['form_type', 'form_id']);
        });

        Schema::table('lab_equipment', function (Blueprint $table) {
            $table->text('specifications')->nullable()->after('description');
            $table->foreignId('end_user_id')->nullable()->after('room_id')->constrained('users')->nullOnDelete();
        });
        Schema::table('lab_consumables', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('storage', 150)->nullable()->after('room_id');
            $table->foreignId('end_user_id')->nullable()->after('storage')->constrained('users')->nullOnDelete();
            $table->string('cabinet', 100)->nullable()->after('unit');
            $table->string('storage_code', 100)->nullable()->after('cabinet')->index();
            $table->string('code_description', 255)->nullable()->after('storage_code');
            $table->string('brand', 150)->nullable()->after('code_description');
            $table->string('cas_number', 100)->nullable()->after('brand');
            $table->string('container_size', 100)->nullable()->after('cas_number');
            $table->text('hazards')->nullable()->after('container_size');
        });
        Schema::table('lab_consumable_lots', function (Blueprint $table) {
            $table->date('manufactured_date')->nullable()->after('received_date');
        });

        Schema::table('lab_safety_orientations', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('attendees');
        });
        Schema::table('lab_first_aid_checks', function (Blueprint $table) {
            $table->string('means_of_verification', 255)->nullable()->after('check_date');
            $table->string('inclusion', 255)->nullable()->after('means_of_verification');
            $table->string('document_path')->nullable()->after('inclusion');
        });
    }

    public function down(): void
    {
        Schema::table('lab_first_aid_checks', function (Blueprint $table) {
            $table->dropColumn(['means_of_verification', 'inclusion', 'document_path']);
        });
        Schema::table('lab_safety_orientations', fn (Blueprint $table) => $table->dropColumn('document_path'));
        Schema::table('lab_consumable_lots', fn (Blueprint $table) => $table->dropColumn('manufactured_date'));
        Schema::table('lab_consumables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_user_id');
            $table->dropColumn(['description', 'storage', 'cabinet', 'storage_code', 'code_description', 'brand', 'cas_number', 'container_size', 'hazards']);
        });
        Schema::table('lab_equipment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('end_user_id');
            $table->dropColumn('specifications');
        });
        Schema::dropIfExists('lab_request_status_histories');
        Schema::table('lab_reagent_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
            $table->dropConstrainedForeignId('bundle_id');
            $table->dropColumn(['received_by_name', 'received_at']);
        });
        Schema::table('lab_equipment_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bundle_id');
            $table->dropColumn('received_by_name');
        });
        Schema::table('lab_reservations', fn (Blueprint $table) => $table->dropConstrainedForeignId('bundle_id'));
        Schema::dropIfExists('lab_request_bundles');
    }
};
