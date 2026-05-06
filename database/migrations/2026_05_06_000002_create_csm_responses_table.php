<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csm_responses', function (Blueprint $table) {
            $table->id();

            // Polymorphic: links to any request module
            $table->morphs('respondable');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Section 1 — Client Info
            $table->enum('client_type', ['citizen', 'business', 'government'])->default('government');
            $table->enum('sex', ['male', 'female'])->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('region_of_residence')->default('Caraga');
            $table->date('date_of_transaction');
            $table->string('office_availed');

            // Section 2 — Service Availed
            $table->json('service_availed');                     // array of checked service keys
            $table->string('service_availed_other')->nullable(); // dynamic per-module label

            // Section 3 — Citizen's Charter
            $table->unsignedTinyInteger('cc1');
            $table->unsignedTinyInteger('cc2')->nullable();      // only when cc1 in [1,2,3]
            $table->unsignedTinyInteger('cc3')->nullable();      // only when cc1 in [1,2,3]

            // Section 4 — SQD (1=Strongly Disagree … 5=Strongly Agree, 6=N/A)
            $table->unsignedTinyInteger('sqd0');
            $table->unsignedTinyInteger('sqd1');
            $table->unsignedTinyInteger('sqd2');
            $table->unsignedTinyInteger('sqd3');
            $table->unsignedTinyInteger('sqd4');
            $table->unsignedTinyInteger('sqd5');
            $table->unsignedTinyInteger('sqd6');
            $table->unsignedTinyInteger('sqd7');
            $table->unsignedTinyInteger('sqd8');

            // Section 5 — Suggestions
            $table->text('suggestions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csm_responses');
    }
};
