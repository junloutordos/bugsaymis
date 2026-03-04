<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no')->unique();
            $table->foreignId('document_type_id')->constrained()->onDelete('restrict');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->string('urgency')->default('Normal'); // Normal | Urgent | Very Urgent
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('current_holder_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('overall_status')->default('In-Transit'); // In-Transit | Completed | Cancelled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
