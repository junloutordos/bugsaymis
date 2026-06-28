<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_module_maturity_scores', function (Blueprint $table) {
            $table->id();
            $table->string('module_key')->index();
            $table->string('dimension');
            $table->unsignedTinyInteger('score')->default(0); // 0-100
            $table->json('criteria_checks');                  // array of bool per sub-criterion
            $table->text('notes')->nullable();
            $table->foreignId('scored_by')->constrained('users');
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->unique(['module_key', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_module_maturity_scores');
    }
};
