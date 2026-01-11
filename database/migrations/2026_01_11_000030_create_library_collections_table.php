<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('library_collections', function (Blueprint $table) {
            $table->id();
            $table->string('collection_type')->nullable(); // Book, Magazine, Journal, Other
            $table->string('title');
            $table->string('author_publisher')->nullable();
            $table->string('accession_number')->nullable()->unique();
            $table->string('category')->nullable();
            $table->string('status')->default('Available');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('library_collections');
    }
};
