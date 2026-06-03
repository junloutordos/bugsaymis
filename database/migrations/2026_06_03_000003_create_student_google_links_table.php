<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_google_links', function (Blueprint $table) {
            $table->id();
            $table->string('google_email')->unique()->comment('@crc.pshs.edu.ph Google Workspace account');
            $table->string('pisaysystemID', 20)->index()->comment('Links to students.pisaysystemID');
            $table->timestamp('linked_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_google_links');
    }
};
