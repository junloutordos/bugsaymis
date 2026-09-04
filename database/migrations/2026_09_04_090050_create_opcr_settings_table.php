<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_settings', function (Blueprint $table) {
            $table->id();
            $table->string('campus_director_name')->nullable();
            $table->string('oic_campus_director_name')->nullable();
            $table->string('executive_director_name')->nullable();
            $table->text('commitment_statement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_settings');
    }
};
