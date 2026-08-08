<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('s3_key');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->unique('s3_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_files');
    }
};
