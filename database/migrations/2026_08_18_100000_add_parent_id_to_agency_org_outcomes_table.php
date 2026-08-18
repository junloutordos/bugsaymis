<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_org_outcomes', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('fiscal_year')
                ->constrained('agency_org_outcomes')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('agency_org_outcomes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
