<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_no')->unique();
            $table->enum('batch_type', ['main', 'bonus'])->default('main');
            $table->date('period_start');
            $table->date('period_end');
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->string('fund_cluster', 50)->nullable();
            $table->string('entity_name')->nullable();
            $table->string('source_main_filename')->nullable();
            $table->string('source_bonus_filename')->nullable();
            $table->string('source_main_s3_key')->nullable();
            $table->string('source_bonus_s3_key')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('status', ['uploaded', 'previewed', 'sending', 'completed', 'failed'])->default('uploaded');
            $table->decimal('totals_gross', 14, 2)->default(0);
            $table->decimal('totals_deductions', 14, 2)->default(0);
            $table->decimal('totals_net', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_batches');
    }
};
