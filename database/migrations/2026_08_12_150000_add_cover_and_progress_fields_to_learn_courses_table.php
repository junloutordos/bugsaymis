<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_courses', function (Blueprint $table) {
            $table->string('cover_photo_s3_key')->nullable()->after('syllabus_body');
            $table->string('cover_preset')->nullable()->after('cover_photo_s3_key');
        });
    }

    public function down(): void
    {
        Schema::table('learn_courses', function (Blueprint $table) {
            $table->dropColumn(['cover_photo_s3_key', 'cover_preset']);
        });
    }
};
