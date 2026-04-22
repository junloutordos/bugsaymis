<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALN Schedule of Personal Properties.
     *
     * Corresponds to "ASSETS - PERSONAL PROPERTIES" section.
     * Covers motor vehicles, jewelry, artworks, shares of stock,
     * cash on hand/in bank, receivables, and other personal property.
     */
    public function up(): void
    {
        Schema::create('saln_personal_properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete();

            // CSC Form: Description of personal property
            $table->string('description')
                ->comment('e.g., Toyota Vios 2019, Diamond Ring, BDO Savings Account');

            // Category helps group for the form
            $table->enum('category', [
                'motor_vehicle',
                'jewelry',
                'artwork',
                'shares_of_stock',
                'cash_on_hand',
                'bank_deposits',
                'receivables',
                'others',
            ])->default('others');

            // CSC Form: Year Acquired
            $table->unsignedSmallInteger('year_acquired')->nullable();

            // CSC Form: Acquisition Cost
            $table->decimal('acquisition_cost', 15, 2)->default(0);

            // Ownership
            $table->enum('owner', ['self', 'spouse', 'dependent'])->default('self');

            $table->timestamps();

            $table->index('saln_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_personal_properties');
    }
};
