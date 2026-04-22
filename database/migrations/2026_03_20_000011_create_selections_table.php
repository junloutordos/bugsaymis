<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('approval_status', ['pending', 'approved', 'disapproved'])->default('pending');
            $table->date('approval_date')->nullable();
            $table->text('disapproval_reason')->nullable();
            $table->timestamps();

            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selections');
    }
};
