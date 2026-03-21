<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomination_id')
                  ->constrained('reward_nominations')
                  ->cascadeOnDelete();

            $table->date('award_date');

            $table->enum('incentive_type', [
                'cash',
                'leave_credits',
                'certificate',
                'plaque',
                'other',
            ]);

            // Numeric value (e.g. PHP amount, number of leave credits)
            $table->decimal('incentive_value', 10, 2)->nullable();

            $table->text('remarks')->nullable();

            // Who recorded this award
            $table->foreignId('recorded_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('award_date');
            $table->index('incentive_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
