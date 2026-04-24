<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Children declared in a SALN record (CSC SALN form — dependents section).
     * One row per child per SALN filing.
     */
    public function up(): void
    {
        Schema::create('saln_children', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete()
                ->comment('SALN record this child entry belongs to');

            $table->string('name', 255)
                ->comment('Full name of the child');

            $table->unsignedTinyInteger('age')
                ->comment('Age of the child in years');

            $table->timestamps();

            $table->index('saln_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_children');
    }
};
