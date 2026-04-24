<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * training_records — tracks training, seminars, and professional
     * development activities attended by faculty and staff.
     *
     * Used by:
     *   - Faculty Loading: training load credits (if applicable)
     *   - IPCR/PMS: supporting evidence for learning & growth KRA
     *   - HR: PDS Part II (Learning and Development)
     *
     * level follows CSC classification for L&D interventions:
     *   international — organized by international body
     *   national      — nationwide scope
     *   regional      — region-level
     *   division      — division/campus level
     *   school        — in-house / school-level
     *
     * hours_earned is the total contact hours (used for PDS and load credit
     * computations). load_units is the derived faculty load contribution
     * (nullable; only populated if this training counts toward loading).
     */
    public function up(): void
    {
        Schema::create('training_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('school_year_id')
                  ->nullable()
                  ->constrained('school_years')
                  ->nullOnDelete()
                  ->comment('School year this training falls under; null = non-academic');

            // Training details
            $table->string('training_title', 255);
            $table->string('organizer', 200)->nullable()
                  ->comment('Sponsoring agency or organization');
            $table->string('venue', 200)->nullable();

            $table->date('date_from');
            $table->date('date_to');

            $table->enum('level', ['international', 'national', 'regional', 'division', 'school'])
                  ->default('school')
                  ->comment('CSC classification of training level');

            // Earned credits
            $table->decimal('hours_earned', 6, 2)->default(0)
                  ->comment('Total contact hours (for PDS and load credit)');
            $table->decimal('load_units', 5, 2)->nullable()
                  ->comment('Faculty load units credited; null if not applicable');

            // Verification
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('HR officer who verified the record');
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'school_year_id']);
            $table->index(['user_id', 'status']);
            $table->index(['date_from', 'date_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_records');
    }
};
