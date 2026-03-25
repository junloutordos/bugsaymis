<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_session_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultation_id')->nullable()->index();
            $table->string('cs_no', 30)->unique();
            $table->date('date_filed');
            $table->string('school_year', 20);
            $table->string('client_name');
            $table->unsignedTinyInteger('client_age')->nullable();
            $table->string('client_sex', 10)->nullable();   // Male / Female
            $table->string('grade_section')->nullable();
            $table->string('batch')->nullable();
            $table->json('referral_strategies');            // ['Walk-In','Referral', ...]
            $table->text('concern')->nullable();
            $table->text('difficulties_presented')->nullable();
            $table->text('counselor_notes')->nullable();
            $table->unsignedBigInteger('accomplished_by'); // users.id of the GSA/Life Coach
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_session_reports');
    }
};
