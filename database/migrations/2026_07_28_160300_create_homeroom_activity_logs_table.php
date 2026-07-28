<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (legacy INT pk); no constraint');
            $table->string('title');
            $table->date('activity_date');
            $table->boolean('is_off_campus')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['section_id', 'activity_date'], 'hmrm_activity_section_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_activity_logs');
    }
};
