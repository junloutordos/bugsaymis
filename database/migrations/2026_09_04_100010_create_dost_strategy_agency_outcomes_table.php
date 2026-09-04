<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_strategy_agency_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dost_strategy_id')->constrained('dost_strategies')->cascadeOnDelete();
            $table->foreignId('agency_outcome_id')->constrained('agency_org_outcomes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['dost_strategy_id', 'agency_outcome_id'], 'strategy_agency_outcome_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_strategy_agency_outcomes');
    }
};
