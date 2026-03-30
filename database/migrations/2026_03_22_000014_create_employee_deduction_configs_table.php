<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_deduction_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained('allowance_types');

            // Override amount (NULL = use allowance_type default)
            $table->decimal('amount', 12, 4)->nullable()
                ->comment('NULL = use allowance_type.default_amount');

            $table->string('reference_no')->nullable()
                ->comment('GSIS policy no., Pag-IBIG loan ref., etc.');

            $table->boolean('is_active')->default(true);

            // Effective date range (NULL = always)
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'allowance_type_id', 'is_active'], 'emp_deduct_cfg_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_deduction_configs');
    }
};
