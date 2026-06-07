<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposal_records', function (Blueprint $table) {
            $table->id();
            $table->string('disposal_number', 50)->unique();
            $table->foreignId('bsr_id')->nullable()->constrained('board_of_survey_reports')->nullOnDelete();
            $table->date('disposal_date');
            $table->enum('disposal_method', ['auction', 'donation', 'destruction', 'condemnation', 'trade_in'])->default('auction');
            $table->decimal('proceeds_amount', 15, 2)->default(0);
            $table->string('recipient')->nullable();
            $table->enum('status', ['draft', 'approved', 'completed'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('disposal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disposal_id')->constrained('disposal_records')->cascadeOnDelete();
            $table->foreignId('property_item_id')->constrained('property_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('appraised_value', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('disposal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_items');
        Schema::dropIfExists('disposal_records');
    }
};
