<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_task_user', function (Blueprint $table) {
            $table->foreignId('committee_task_id')->constrained('committee_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['committee_task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_task_user');
    }
};
