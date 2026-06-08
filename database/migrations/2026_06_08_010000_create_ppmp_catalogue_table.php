<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppmp_catalogue', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('stock_number', 100);
            $table->text('description');
            $table->string('unit', 50);
            $table->decimal('unit_cost', 15, 2);
            $table->date('price_validity_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->unique(['fiscal_year', 'stock_number']);
            $table->index('fiscal_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_catalogue');
    }
};
