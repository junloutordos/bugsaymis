<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_rating_period_v2_id')
                ->constrained('ipcr_rating_periods_v2')
                ->cascadeOnDelete();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_templates');
    }
};
