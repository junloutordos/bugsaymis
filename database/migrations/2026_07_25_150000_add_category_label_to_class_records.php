<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_records', function (Blueprint $table) {
            $table->string('category_label', 100)->nullable()->after('subject_name');
        });
    }

    public function down(): void
    {
        Schema::table('class_records', function (Blueprint $table) {
            $table->dropColumn('category_label');
        });
    }
};
