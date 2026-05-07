<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stanine_lookup', function (Blueprint $table) {
            $table->id();
            $table->decimal('percentage', 5, 2)->comment('e.g. 100.00, 99.00 ... 39.00');
            $table->decimal('grade_equivalent', 5, 3)->comment('e.g. 1.000, 1.027 ... 4.510');
            $table->string('adjectival_equivalent')->comment('e.g. Excellent, Very Good, Failed');
            $table->timestamps();

            $table->index('percentage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stanine_lookup');
    }
};
