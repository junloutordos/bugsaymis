<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dost_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dost_pillar_id')->constrained('dost_pillars')->onDelete('cascade');
            $table->foreignId('agency_outcome_id')->nullable()->constrained('agency_org_outcomes')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dost_strategies');
    }
};
