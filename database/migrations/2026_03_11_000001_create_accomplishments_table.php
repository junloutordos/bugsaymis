<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accomplishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ipcr_plan_id')->nullable()->constrained('employee_ipcrs_plan')->nullOnDelete();
            $table->date('accomplishment_date');
            $table->text('description');
            $table->timestamps();

            $table->index(['user_id', 'accomplishment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accomplishments');
    }
};
