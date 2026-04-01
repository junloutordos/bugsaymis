<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Service Credits — exclusively for Teaching personnel.
     *
     * Teaching staff earn Service Credits (CTO) in lieu of overtime/extra work.
     * These are governed by CSC-CHED Joint Circular No. 1, s. 2012 and CSC MC 41 s. 1998.
     * Service Credits are NOT the same as VL/SL and do NOT accrue monthly.
     *
     * Each row represents a single earning event (e.g., extra teaching load,
     * committee work, school activity, etc.).
     */
    public function up(): void
    {
        Schema::create('service_credit_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Date the service was rendered
            $table->date('service_date');

            // Nature of extra service
            $table->enum('service_type', [
                'extra_teaching_load',   // Beyond regular load
                'committee_work',        // Designated committee duties
                'school_activity',       // Programs, events, graduation, etc.
                'special_assignment',    // School head directive
                'other',
            ]);

            // Hours of service rendered (raw)
            $table->decimal('hours_rendered', 6, 2);

            // Converted days (hours / 8, rounded to nearest 0.5 per CSC rules)
            $table->decimal('days_equivalent', 6, 4)
                ->comment('hours_rendered / 8, rounded per CSC rules');

            $table->enum('status', [
                'pending',   // Submitted, awaiting verification
                'approved',  // Verified by HR / Principal; added to CTO credit balance
                'rejected',  // Not approved
                'consumed',  // Used as CTO leave
                'expired',   // Lapsed beyond the allowed usage window (1 year from earning)
            ])->default('pending');

            // When consumed as CTO, link to the leave application
            $table->foreignId('leave_application_id')->nullable()
                ->constrained('leave_applications')->nullOnDelete();

            // HR / principal who approved the earning
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->date('approved_at')->nullable();

            // Expiry — 1 year from service_date per CSC policy
            $table->date('expires_at')->nullable()
                ->comment('Unused credits expire after 1 year from service_date');

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_credit_records');
    }
};
