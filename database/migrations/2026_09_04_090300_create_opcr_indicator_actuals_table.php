<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_indicator_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opcr_indicator_id')->constrained('opcr_indicators')->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->string('value')->nullable();
            $table->timestamps();
            $table->unique(['opcr_indicator_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_indicator_actuals');
    }
};
