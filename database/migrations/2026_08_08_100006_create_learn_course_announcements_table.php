<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_course_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['learn_course_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_course_announcements');
    }
};
