<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->text('explanation_text')->nullable()->after('image');
            $table->string('explanation_image')->nullable()->after('explanation_text')->comment('S3 key');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn(['explanation_text', 'explanation_image']);
        });
    }
};
