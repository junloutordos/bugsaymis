<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            // Which faculty-load assignment type feeds this plan's
            // individual_target (teaching|research|admin|cocurricular|committee).
            // NULL = not a faculty framework row.
            $table->string('load_source', 20)->nullable()->after('rated_by');
        });
    }

    public function down(): void
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->dropColumn('load_source');
        });
    }
};
