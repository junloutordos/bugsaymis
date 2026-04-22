<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('library_attendances', function (Blueprint $table) {
            $table->id();
            $table->string('pisay_systemid')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('student_name')->nullable();
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('library_attendances');
    }
};
