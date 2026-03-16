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
        Schema::create('pds_voluntary_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_id')->constrained('pds')->onDelete('cascade');
            $table->string('organization');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->integer('hours')->nullable();
            $table->string('nature_of_work')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_voluntary_work');
    }
};
