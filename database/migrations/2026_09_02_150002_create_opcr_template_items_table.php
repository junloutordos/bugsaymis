<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opcr_template_id')
                ->constrained('opcr_templates')
                ->cascadeOnDelete();
            $table->string('strategy_label');
            $table->text('output_outcome');
            $table->text('success_indicator')->nullable();
            $table->text('target')->nullable();
            $table->text('rating_scale_quality')->nullable();
            $table->text('rating_scale_efficiency')->nullable();
            $table->text('rating_scale_timeliness')->nullable();
            // Entered by OCD/HR per item, validated to sum to the division's
            // Strategic % — see IpcrWorkflowServiceV2::assertWeightsValid().
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_template_items');
    }
};
