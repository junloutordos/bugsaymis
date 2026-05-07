<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pds_work_experience_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_id')->constrained('pds')->cascadeOnDelete();
            $table->string('from_date', 50)->nullable();
            $table->string('to_date', 50)->nullable();
            $table->string('position')->nullable();
            $table->string('office_unit')->nullable()->comment('Name of Office/Unit');
            $table->string('immediate_supervisor')->nullable();
            $table->text('agency_organization_location')->nullable()->comment('Name of Agency/Organization and Location');
            $table->json('accomplishments')->nullable()->comment('List of accomplishments (array of strings)');
            $table->json('summary_of_duties')->nullable()->comment('Summary of actual duties (array of strings)');
            $table->unsignedTinyInteger('sort_order')->default(0)->comment('0 = most recent first');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pds_work_experience_sheets');
    }
};
