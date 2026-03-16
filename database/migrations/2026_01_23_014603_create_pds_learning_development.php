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
        Schema::create('pds_training', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pds_id')->constrained('pds')->onDelete('cascade');
            $table->string('training_title');
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->decimal('hours', 5, 2)->nullable();
            $table->string('training_type')->nullable();
            $table->string('conducted_by')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pds_learning_development');
    }
};
