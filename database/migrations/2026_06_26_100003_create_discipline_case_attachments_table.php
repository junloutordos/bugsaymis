<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discipline_case_attachments')) {
            return;
        }

        Schema::create('discipline_case_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('discipline_case_id')->index();
            $table->string('file_name');
            $table->string('file_path');                 // S3 key
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_case_attachments');
    }
};
