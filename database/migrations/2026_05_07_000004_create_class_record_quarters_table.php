<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_quarters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_id')->constrained('class_records')->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter')->comment('1-4');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['class_record_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_quarters');
    }
};
