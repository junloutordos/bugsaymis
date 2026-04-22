<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Main SALN record — one per employee per filing year.
     *
     * Status lifecycle:
     *   draft → submitted → under_review → approved → filed
     *                   ↘ returned ↗  (loops back to draft)
     */
    public function up(): void
    {
        Schema::create('saln_records', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Employee who filed this SALN');

            // Filing period
            $table->unsignedSmallInteger('year')
                ->comment('Calendar year covered by this SALN (e.g. 2025)');

            $table->date('as_of_date')
                ->comment('Assets/liabilities declared "as of" this date (usually Dec 31 of the covered year)');

            // Lifecycle status
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'returned',
                'filed',
            ])->default('draft')->index();

            // Auto-computed financial totals (stored for fast reporting)
            $table->decimal('total_real_properties',     15, 2)->default(0);
            $table->decimal('total_personal_properties', 15, 2)->default(0);
            $table->decimal('total_assets',              15, 2)->default(0);
            $table->decimal('total_liabilities',         15, 2)->default(0);
            $table->decimal('net_worth',                 15, 2)->default(0)
                ->comment('total_assets - total_liabilities');

            // Spouse info (part of CSC SALN form)
            $table->string('spouse_name')->nullable();
            $table->string('spouse_position')->nullable();
            $table->string('spouse_office')->nullable();
            $table->string('spouse_government_id')->nullable();

            // Workflow timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('filed_at')->nullable();

            // Reviewers
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('SALN Committee member who reviewed');

            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('SALN Committee member who approved');

            $table->foreignId('filed_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('HR officer who marked as filed');

            $table->timestamps();

            // Enforce one SALN per employee per year
            $table->unique(['user_id', 'year']);

            // Reporting indexes
            $table->index(['year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_records');
    }
};
