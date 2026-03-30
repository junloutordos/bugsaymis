<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained('payroll_records')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types');

            $table->decimal('amount', 12, 4)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->string('note')->nullable();

            $table->timestamps();

            $table->index(['payroll_record_id', 'allowance_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_allowances');
    }
};
