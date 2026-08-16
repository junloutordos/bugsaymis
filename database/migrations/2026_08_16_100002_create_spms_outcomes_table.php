<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('outcome');
            $table->string('sub_outcome')->nullable();
            $table->string('function_type'); // 'strategic' | 'core' | 'support'
            $table->unsignedSmallInteger('fiscal_year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_outcomes');
    }
};
