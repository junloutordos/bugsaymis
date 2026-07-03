<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reagent Request Form (PSHS-00-F-CID-19). Header + line items.
 * Requires SDS-read acknowledgement per reagent (CIM 4.4 §3.1.11).
 * On release, deducts from reagent inventory (FEFO) via lab_stock_movements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_reagent_requests', function (Blueprint $table) {
            $table->id();
            $table->string('control_no', 40)->unique();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->string('grade_level_section', 100)->nullable();
            $table->unsignedSmallInteger('number_of_students')->nullable();
            $table->string('subject', 150)->nullable();
            $table->string('concurrent_topic', 200)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('teacher_in_charge', 150)->nullable();
            $table->string('venue', 150)->nullable();
            $table->date('date_start');
            $table->date('date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name', 150)->nullable();
            $table->string('requester_type', 20)->default('teacher');
            $table->json('student_group')->nullable();
            $table->boolean('sds_acknowledged')->default(false);
            $table->foreignId('endorsed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('endorsed_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete(); // releasing unit SRA
            $table->timestamp('released_at')->nullable();
            // pending | endorsed | approved | released | completed | declined | cancelled
            $table->string('status', 20)->default('pending')->index();
            $table->text('decline_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_reagent_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_reagent_request_id')->constrained('lab_reagent_requests')->cascadeOnDelete();
            $table->foreignId('lab_consumable_id')->nullable()->constrained('lab_consumables')->nullOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('reagent');
            $table->boolean('sds_read')->default(false);
            $table->string('issued_amount', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_reagent_request_items');
        Schema::dropIfExists('lab_reagent_requests');
    }
};
