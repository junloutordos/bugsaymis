<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('category', ['individual', 'team'])->default('individual');
            $table->enum('type', ['monetary', 'non_monetary', 'mixed'])->default('non_monetary');

            $table->text('criteria')->nullable();

            $table->enum('frequency', ['monthly', 'quarterly', 'annual', 'ad_hoc'])->default('annual');

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index('category');
            $table->index('frequency');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_types');
    }
};
