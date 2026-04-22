<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('birthdate')->nullable();
            $table->text('address')->nullable();
            $table->string('civil_status')->nullable(); // single, married, widowed, separated
            $table->string('email')->unique();
            $table->string('contact_number')->nullable();
            $table->string('eligibility')->nullable(); // CSC eligibility type
            $table->string('prc_license_no')->nullable();
            $table->string('school')->nullable();
            $table->string('course')->nullable();
            $table->year('year_graduated')->nullable();
            $table->string('source')->nullable(); // walk-in, referral, jobstreet, csc portal, etc.
            $table->enum('status', ['active', 'blacklisted', 'hired', 'withdrawn'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
