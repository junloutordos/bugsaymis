<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_ipcr_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained('spms_ipcrs')->cascadeOnDelete();
            $table->string('function_type'); // 'strategic' | 'core' | 'support'
            $table->string('source_type')->nullable(); // e.g. App\Models\LoadAssignment, App\Models\Committee
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('success_indicator');
            $table->text('target')->nullable();
            $table->text('rubric_text')->nullable();
            $table->decimal('weight_pct', 5, 2)->default(0);
            $table->decimal('actual_q', 5, 2)->nullable();
            $table->decimal('actual_e', 5, 2)->nullable();
            $table->decimal('actual_t', 5, 2)->nullable();
            $table->decimal('rating_q', 4, 2)->nullable();
            $table->decimal('rating_e', 4, 2)->nullable();
            $table->decimal('rating_t', 4, 2)->nullable();
            $table->decimal('rating_avg', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_ipcr_targets');
    }
};
