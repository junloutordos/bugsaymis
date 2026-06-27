<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rh_fee_ledger')) {
            return;
        }

        Schema::create('rh_fee_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rh_intern_id')->index();
            $table->tinyInteger('period_month');   // 1-12
            $table->smallInteger('period_year');
            $table->decimal('lodging_fee', 8, 2)->default(0);
            $table->decimal('appliance_fee', 8, 2)->default(0);
            $table->decimal('total_fee', 8, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rh_intern_id', 'period_month', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_fee_ledger');
    }
};
