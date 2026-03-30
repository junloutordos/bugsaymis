<?php

// database/migrations/2025_09_28_000000_create_ipcr_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('ipcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_distribution_plan_id')->constrained()->cascadeOnDelete();

            // Workflow fields
            $table->text('target')->nullable(); // submitted by user
            $table->enum('target_status', ['draft','submitted','approved','rejected'])->default('draft');
            $table->timestamp('target_submitted_at')->nullable();
            $table->timestamp('target_reviewed_at')->nullable();

            // After approval
            $table->text('accomplishment')->nullable();
            $table->integer('self_rating')->nullable(); // given by user
            $table->integer('supervisor_rating')->nullable(); // given by supervisor
            $table->timestamp('accomplishment_submitted_at')->nullable();
            $table->timestamp('accomplishment_reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ipcrs');
    }
};
