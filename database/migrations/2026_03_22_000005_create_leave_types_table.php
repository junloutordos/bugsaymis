<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()
                ->comment('VL, SL, ML, PL, SPL, FL, CTO, SL-Special, etc.');
            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('days_per_year', 6, 2)->nullable()
                ->comment('NULL = no fixed annual allocation (e.g. Maternity=105)');
            $table->boolean('is_creditable')->default(true)
                ->comment('Counted toward leave credit balance');
            $table->boolean('is_deductible')->default(true)
                ->comment('Deducts from leave credit balance when filed');
            $table->boolean('requires_approval')->default(true);
            $table->unsignedSmallInteger('min_days_notice')->default(0)
                ->comment('Advance notice in days required before filing');
            $table->decimal('max_days_per_application', 6, 2)->nullable()
                ->comment('NULL = no per-application cap');

            // Affects payroll
            $table->boolean('with_pay')->default(true)
                ->comment('False = AWOL/without pay deduction');

            // Which employment types can avail this leave
            $table->json('applicable_employment_types')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
