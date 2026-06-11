<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oed_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 30);
            $table->foreign('category_code')->references('code')->on('oed_issuance_categories')->restrictOnDelete();

            $table->string('reference_no', 100)->nullable();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->date('issued_date');
            $table->date('effective_date')->nullable();

            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 100)->default('application/pdf');
            $table->unsignedBigInteger('file_size')->nullable();

            $table->string('status', 20)->default('active');
            $table->foreignId('superseded_by_id')->nullable()->constrained('oed_issuances')->nullOnDelete();

            $table->string('recipient_type', 20)->default('all');

            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['category_code', 'issued_date']);
            $table->index('status');
            $table->index('reference_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oed_issuances');
    }
};
