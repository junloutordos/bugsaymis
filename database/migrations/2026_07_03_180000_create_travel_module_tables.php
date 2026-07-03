<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('control_no')->nullable()->unique();
            $table->foreignId('traveler_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('division_chief_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('travel_type')->default('oed_initiated');
            $table->string('funding_source')->default('campus_funds');
            $table->string('fund_cluster')->nullable();
            $table->string('travel_mode')->default('land');
            $table->string('origin')->nullable();
            $table->string('destination');
            $table->text('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('passenger_count')->default(1);
            $table->boolean('requires_cash_advance')->default(false);
            $table->decimal('cash_advance_amount', 14, 2)->default(0);
            $table->string('cash_advance_status')->nullable();
            $table->string('liquidation_status')->nullable();
            $table->text('liquidation_remarks')->nullable();
            $table->foreignId('travel_order_issuance_id')->nullable()->constrained('issuances')->nullOnDelete();
            $table->foreignId('special_order_issuance_id')->nullable()->constrained('issuances')->nullOnDelete();
            $table->foreignId('vehicle_request_id')->nullable()->constrained('vehicle_requests')->nullOnDelete();
            $table->foreignId('ors_id')->nullable()->constrained('obligation_requests')->nullOnDelete();
            $table->foreignId('dv_id')->nullable()->constrained('disbursement_vouchers')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('return_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('division_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('division_approved_at')->nullable();
            $table->foreignId('fad_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fad_reviewed_at')->nullable();
            $table->foreignId('ocd_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ocd_approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('liquidated_at')->nullable();
            $table->timestamps();

            $table->index(['traveler_id', 'status']);
            $table->index(['status', 'start_date']);
            $table->index('travel_type');
        });

        Schema::create('travel_itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained('travel_requests')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->date('travel_date')->nullable();
            $table->string('places_to_visit')->nullable();
            $table->time('departure_time')->nullable();
            $table->time('arrival_time')->nullable();
            $table->string('means_of_transportation')->nullable();
            $table->decimal('transportation_cost', 14, 2)->default(0);
            $table->decimal('per_diem', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['travel_request_id', 'sort_order']);
        });

        Schema::create('travel_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained('travel_requests')->cascadeOnDelete();
            $table->string('type');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('s3_key');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['travel_request_id', 'type']);
        });

        Schema::create('travel_flight_authorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_request_id')->constrained('travel_requests')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->string('passenger_name')->nullable();
            $table->string('route')->nullable();
            $table->date('preferred_departure_date')->nullable();
            $table->date('preferred_return_date')->nullable();
            $table->decimal('estimated_airfare', 14, 2)->default(0);
            $table->text('booking_notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('rule_key');
            $table->string('label');
            $table->json('value')->nullable();
            $table->string('source_url')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source', 'rule_key', 'effective_date'], 'travel_policy_source_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_policy_rules');
        Schema::dropIfExists('travel_flight_authorities');
        Schema::dropIfExists('travel_attachments');
        Schema::dropIfExists('travel_itinerary_items');
        Schema::dropIfExists('travel_requests');
    }
};
