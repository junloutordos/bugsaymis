<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->foreignId('assigned_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = permanent/open-ended
            $table->enum('status', ['pending', 'active', 'completed', 'terminated'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('assigned_office_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
