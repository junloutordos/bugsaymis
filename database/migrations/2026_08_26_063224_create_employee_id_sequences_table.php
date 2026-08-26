<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One row per hire-year, tracking the last sequence number issued for
        // employee_idno_new (E13-YYYY-MM-XXX). Locked with lockForUpdate()
        // during generation so concurrent logins/HR actions can't collide.
        Schema::create('employee_id_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('hired_year')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_id_sequences');
    }
};
