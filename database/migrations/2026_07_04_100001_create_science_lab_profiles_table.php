<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Science laboratory profile — a sidecar on the existing `rooms` table.
 * A room becomes a managed science laboratory when it has a profile here.
 * Reuses the room's name/code/building/capacity; carries lab-specific
 * attributes (owning academic unit, AUH, in-charge SRS/SRA, safety doc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('science_lab_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->unique()->constrained('rooms')->cascadeOnDelete();
            $table->string('unit', 100)->nullable(); // Chemistry, Biology, Physics, Research, General
            $table->foreignId('auh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('in_charge_user_id')->nullable()->constrained('users')->nullOnDelete(); // SRS/SRA
            $table->string('safety_procedure_path')->nullable(); // S3 key
            $table->json('first_aid_checklist')->nullable();
            $table->string('status', 20)->default('active'); // active | inactive
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('science_lab_profiles');
    }
};
