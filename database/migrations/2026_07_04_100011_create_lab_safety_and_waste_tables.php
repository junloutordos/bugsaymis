<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laboratory Safety (CIM 4.4 §3.2) and Waste Management (§3.1.12 / §4.1.9):
 * safety procedures (PCO/AUH-determined, posted), student safety-orientation
 * records, first-aid kit checks, and laboratory waste disposal logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_safety_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('unit', 100)->nullable();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('document_path')->nullable(); // S3
            $table->foreignId('determined_by_id')->nullable()->constrained('users')->nullOnDelete(); // PCO
            $table->boolean('is_posted')->default(false);
            $table->date('effective_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('lab_safety_orientations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->date('orientation_date');
            $table->foreignId('conducted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('grade_level_section', 100)->nullable();
            $table->string('topic', 200)->nullable();
            $table->json('attendees')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_first_aid_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->date('check_date');
            $table->json('items')->nullable(); // [{name, present, qty, expiry}]
            $table->boolean('is_complete')->default(false);
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_waste_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('unit', 100)->nullable();
            $table->date('waste_date');
            $table->string('waste_type', 20); // chemical | biological | sharps | general | other
            $table->string('description');
            $table->string('quantity', 100)->nullable();
            $table->string('disposal_method', 200)->nullable();
            $table->foreignId('disposed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_waste_logs');
        Schema::dropIfExists('lab_first_aid_checks');
        Schema::dropIfExists('lab_safety_orientations');
        Schema::dropIfExists('lab_safety_procedures');
    }
};
