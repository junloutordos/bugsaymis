<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_outcome_id')->constrained('agency_org_outcomes')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->string('target')->nullable();
            $table->foreignId('division_id')->constrained('divisions')->onDelete('cascade');
            $table->decimal('budget', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};
