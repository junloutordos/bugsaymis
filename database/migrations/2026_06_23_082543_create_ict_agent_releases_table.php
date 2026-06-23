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
        Schema::create('ict_agent_releases', function (Blueprint $table) {
            $table->id();
            $table->string('version', 20)->unique();
            $table->string('s3_key');
            $table->string('sha256', 64);
            $table->text('release_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_agent_releases');
    }
};
