<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ict_remediation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('metric_key');
            $table->string('condition');
            $table->string('action');
            $table->boolean('auto_execute')->default(false);
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_remediation_rules');
    }
};
