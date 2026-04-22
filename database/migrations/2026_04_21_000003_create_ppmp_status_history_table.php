<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppmp_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ppmp_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->unsignedBigInteger('acted_by');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('ppmp_id')->references('id')->on('ppmp')->cascadeOnDelete();
            $table->foreign('acted_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('ppmp_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_status_history');
    }
};
