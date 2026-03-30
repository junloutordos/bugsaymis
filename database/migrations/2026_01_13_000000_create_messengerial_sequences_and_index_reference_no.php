<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('messengerial_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('division_code', 20);
            $table->integer('year');
            $table->integer('last_number')->default(0);
            $table->timestamps();
            $table->unique(['division_code', 'year']);
        });

        // Ensure reference_no is unique on messengerial_requests
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('messengerial_requests', 'reference_no')) return;
            $table->unique('reference_no');
        });
    }

    public function down()
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            if (Schema::hasColumn('messengerial_requests', 'reference_no')) {
                $table->dropUnique(['reference_no']);
            }
        });

        Schema::dropIfExists('messengerial_sequences');
    }
};
