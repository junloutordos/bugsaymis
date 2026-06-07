<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cid_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->unsignedInteger('section_id')->nullable()->comment('Soft ref → sections.id');
            $table->unsignedBigInteger('subject_id')->nullable()->comment('Soft ref → subjects.id');
            $table->string('title');
            $table->enum('type', ['assessment', 'meeting', 'event', 'training', 'other'])->default('assessment');
            $table->date('scheduled_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['school_year_id', 'scheduled_date']);
            $table->index(['section_id', 'scheduled_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cid_schedules');
    }
};
