<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->foreignId('dost_sub_strategy_id')->nullable()->constrained('dost_sub_strategies')->restrictOnDelete();
            $table->foreignId('agency_outcome_id')->constrained('agency_org_outcomes')->restrictOnDelete();
            $table->foreignId('performance_indicator_id')->nullable()->constrained('performance_indicators')->nullOnDelete();
            $table->text('description');
            $table->string('target')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('rating_quality', 3, 2)->nullable();
            $table->decimal('rating_efficiency', 3, 2)->nullable();
            $table->decimal('rating_timeliness', 3, 2)->nullable();
            $table->decimal('rating_average', 3, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_indicators');
    }
};
