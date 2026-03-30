<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALN Financial Connections and Business Interests.
     *
     * Corresponds to the "FINANCIAL CONNECTIONS & BUSINESS INTERESTS"
     * section of the CSC SALN form.
     *
     * Covers: Name of Entity/Business, Business Address, Nature of Business,
     * Date Acquired, Acquisition Cost, Present Fair Market Value.
     */
    public function up(): void
    {
        Schema::create('saln_business_interests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete();

            // CSC Form: "Name of Entity / Business"
            $table->string('entity_name');

            // CSC Form: "Business Address"
            $table->string('business_address');

            // CSC Form: "Nature of Business/Financial Connection"
            $table->string('nature_of_business');

            // CSC Form: "Date Acquired"
            $table->date('date_acquired')->nullable();

            // CSC Form: "Acquisition Cost"
            $table->decimal('acquisition_cost', 15, 2)->default(0);

            // CSC Form: "Present Fair Market Value"
            $table->decimal('present_fair_market_value', 15, 2)->default(0);

            // Ownership: self or spouse
            $table->enum('owner', ['self', 'spouse'])->default('self');

            $table->timestamps();

            $table->index('saln_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_business_interests');
    }
};
