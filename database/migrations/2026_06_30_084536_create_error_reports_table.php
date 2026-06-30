<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no', 20)->unique()->comment('ERR-YYYY-NNNN');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description');
            $table->string('page_url', 500)->nullable();
            $table->json('browser_info')->nullable()->comment('user agent, viewport, platform');
            $table->string('screenshot_path', 500)->nullable()->comment('S3 key');
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('action_taken')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
