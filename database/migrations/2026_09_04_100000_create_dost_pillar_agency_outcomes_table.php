<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_pillar_agency_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dost_pillar_id')->constrained('dost_pillars')->cascadeOnDelete();
            $table->foreignId('agency_outcome_id')->constrained('agency_org_outcomes')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['dost_pillar_id', 'agency_outcome_id'], 'pillar_agency_outcome_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_pillar_agency_outcomes');
    }
};
