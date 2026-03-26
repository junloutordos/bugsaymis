<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALN Schedule of Real Properties.
     *
     * Corresponds to the "ASSETS - REAL PROPERTIES" section of the CSC SALN form.
     * Columns follow CSC format: Kind, Exact Location, Assessed Value,
     * Current Fair Market Value, Acquisition Year/Mode/Cost.
     */
    public function up(): void
    {
        Schema::create('saln_real_properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete();

            // CSC Form: "Kind" — land, building, condominium, etc.
            $table->string('kind', 100)
                ->comment('e.g., Land, Residential Building, Agricultural Land');

            // CSC Form: "Exact Location"
            $table->string('exact_location')
                ->comment('Street, Barangay, City/Municipality, Province');

            // CSC Form: "Assessed Value" (from tax declaration)
            $table->decimal('assessed_value', 15, 2)->default(0);

            // CSC Form: "Current Fair Market Value"
            $table->decimal('current_fair_market_value', 15, 2)->default(0);

            // CSC Form: "Year Acquired"
            $table->unsignedSmallInteger('year_acquired')->nullable();

            // CSC Form: "Mode of Acquisition"
            $table->enum('acquisition_mode', [
                'purchased',
                'inherited',
                'gift',
                'others',
            ])->default('purchased');

            $table->string('acquisition_mode_other', 100)->nullable()
                ->comment('Specify if acquisition_mode = others');

            // CSC Form: "Acquisition Cost"
            $table->decimal('acquisition_cost', 15, 2)->default(0);

            // Ownership type (self or spouse/dependents)
            $table->enum('owner', ['self', 'spouse', 'dependent'])->default('self');

            $table->timestamps();

            $table->index('saln_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_real_properties');
    }
};
