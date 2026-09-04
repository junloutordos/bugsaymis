<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcr_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->unique();
            $table->string('period_label');
            $table->boolean('is_current')->default(false);
            $table->string('campus_director_name')->nullable();
            $table->string('oic_campus_director_name')->nullable();
            $table->string('executive_director_name')->nullable();
            $table->text('commitment_statement')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opcr_periods');
    }
};
