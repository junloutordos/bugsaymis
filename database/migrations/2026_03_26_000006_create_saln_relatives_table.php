<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALN Relatives in Government Service.
     *
     * Corresponds to the "RELATIVES IN THE GOVERNMENT SERVICE"
     * section of the CSC SALN form.
     *
     * Covers relatives within the 4th civil degree of consanguinity/affinity
     * who are in government service.
     */
    public function up(): void
    {
        Schema::create('saln_relatives', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete();

            // CSC Form: "Name"
            $table->string('name');

            // CSC Form: "Relationship"
            $table->string('relationship', 100)
                ->comment('e.g., Spouse, Child, Parent, Sibling, Cousin');

            // CSC Form: "Position"
            $table->string('position');

            // CSC Form: "Name of Agency/Office"
            $table->string('agency_office');

            // CSC Form: "Address of Agency/Office"
            $table->string('agency_address')->nullable();

            $table->timestamps();

            $table->index('saln_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_relatives');
    }
};
