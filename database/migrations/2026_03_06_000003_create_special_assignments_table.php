<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('special_assignment_user', function (Blueprint $table) {
            $table->foreignId('special_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('task')->nullable();
            $table->primary(['special_assignment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_assignment_user');
        Schema::dropIfExists('special_assignments');
    }
};
