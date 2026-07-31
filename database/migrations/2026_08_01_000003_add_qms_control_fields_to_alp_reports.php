<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alp_reports', function (Blueprint $table) {
            $table->string('form_code', 80)->nullable()->after('period');
            $table->unsignedSmallInteger('version_no')->default(2)->after('form_code');
            $table->unsignedSmallInteger('revision_no')->default(0)->after('version_no');
        });
    }

    public function down(): void
    {
        Schema::table('alp_reports', function (Blueprint $table) {
            $table->dropColumn(['form_code', 'version_no', 'revision_no']);
        });
    }
};
