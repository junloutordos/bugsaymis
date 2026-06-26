<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipline_interventions')) {
            return;
        }

        Schema::create('discipline_interventions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discipline_case_id')->index();
            $table->string('intervention_type');         // Warning, Counseling, Detention, Suspension, etc.
            $table->text('description')->nullable();
            $table->unsignedBigInteger('imposed_by')->nullable();
            $table->timestamp('imposed_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active'); // active | completed | cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_interventions');
    }
};
