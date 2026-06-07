<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_of_survey_reports', function (Blueprint $table) {
            $table->id();
            $table->string('bsr_number', 50)->unique();
            $table->date('inspection_date');
            $table->json('board_members')->nullable();
            $table->text('findings')->nullable();
            $table->text('recommendation')->nullable();
            $table->enum('status', ['draft', 'approved', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bsr_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bsr_id')->constrained('board_of_survey_reports')->cascadeOnDelete();
            $table->foreignId('property_item_id')->constrained('property_items')->restrictOnDelete();
            $table->enum('condition', ['poor', 'damaged', 'obsolete', 'beyond_repair', 'lost'])->default('poor');
            $table->enum('recommendation', ['repair', 'dispose', 'write_off'])->default('dispose');
            $table->decimal('appraised_value', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('bsr_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bsr_items');
        Schema::dropIfExists('board_of_survey_reports');
    }
};
