<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_incidents')) {
            return;
        }

        Schema::create('rh_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_intern_id')->index();
            $table->enum('incident_type', ['health', 'behavioral', 'psychological']);
            $table->text('description');
            $table->text('initial_intervention')->nullable();
            $table->string('referred_to')->nullable();  // nurse|guidance|parent|ssd_chief|discipline_officer
            $table->text('follow_up_notes')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open')->index();
            $table->unsignedBigInteger('logged_by')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_incidents');
    }
};
