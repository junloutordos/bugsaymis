<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->json('changes')->nullable()->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            $table->dropColumn('changes');
        });
    }
};
