<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_weight_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // 'opcr' | 'dpcr' | 'ipcr'
            $table->foreignId('division_id')->nullable()->constrained('divisions')->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('strategic_pct', 5, 2);
            $table->decimal('core_pct', 5, 2);
            $table->decimal('support_pct', 5, 2);
            $table->json('core_subweights')->nullable(); // DPCR-only: {core_duties_pct, student_eval_pct, supervisor_eval_pct}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_weight_profiles');
    }
};
