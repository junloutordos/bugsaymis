<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            // Manually entered per row when attached to a V2 IPCR; NULL
            // until then. v1 code never reads these — safe additive change.
            $table->decimal('weight_percent', 5, 2)->nullable()->after('load_source');
            $table->text('rating_scale_quality')->nullable()->after('weight_percent');
            $table->text('rating_scale_efficiency')->nullable()->after('rating_scale_quality');
            $table->text('rating_scale_timeliness')->nullable()->after('rating_scale_efficiency');
        });
    }

    public function down(): void
    {
        Schema::table('work_distribution_plans', function (Blueprint $table) {
            $table->dropColumn(['weight_percent', 'rating_scale_quality', 'rating_scale_efficiency', 'rating_scale_timeliness']);
        });
    }
};
