<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('category', [
                'appointment', 'pds', 'service_record', 'performance',
                'eligibility', 'training', 'medical', 'leave', 'other'
            ])->default('other')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('document_date')->nullable();
            $table->string('file_path')->nullable()->comment('Local storage path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable()->comment('bytes');
            $table->timestamps();
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
