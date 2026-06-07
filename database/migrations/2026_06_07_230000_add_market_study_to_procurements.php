<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->string('market_study_s3_key', 500)->nullable()->after('ppmp_id');
            $table->string('market_study_filename', 255)->nullable()->after('market_study_s3_key');
        });
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropColumn(['market_study_s3_key', 'market_study_filename']);
        });
    }
};
