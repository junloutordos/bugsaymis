<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropForeign(['agency_outcome_id']);
        });

        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->foreign('agency_outcome_id')
                ->references('id')->on('agency_org_outcomes')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropForeign(['agency_outcome_id']);
        });

        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->foreign('agency_outcome_id')
                ->references('id')->on('agency_org_outcomes')
                ->onDelete('cascade');
        });
    }
};
