<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppmp_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ppmp_id');
            $table->string('code', 50)->nullable();
            $table->text('description');
            $table->string('unit', 50);
            $table->string('category', 30); // goods, infrastructure, consulting_services
            $table->decimal('unit_cost', 15, 2)->default(0);

            // Monthly quantities (Jan–Dec)
            $table->integer('jan')->default(0);
            $table->integer('feb')->default(0);
            $table->integer('mar')->default(0);
            $table->integer('apr')->default(0);
            $table->integer('may')->default(0);
            $table->integer('jun')->default(0);
            $table->integer('jul')->default(0);
            $table->integer('aug')->default(0);
            $table->integer('sep')->default(0);
            $table->integer('oct')->default(0);
            $table->integer('nov')->default(0);
            $table->integer('dec')->default(0);

            // Computed (denormalized for query performance, recomputed on save)
            $table->integer('total_quantity')->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);

            $table->string('procurement_method', 50)->default('competitive_bidding');
            $table->boolean('is_ps_dbm')->default(false);
            $table->text('remarks')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('ppmp_id')->references('id')->on('ppmp')->cascadeOnDelete();
            $table->index('ppmp_id');
            $table->index(['ppmp_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_items');
    }
};
