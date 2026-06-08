<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp_catalogue', function (Blueprint $table) {
            // Which part of the APP-CSE form this item belongs to (1 or 2)
            $table->unsignedTinyInteger('part')->default(1)->after('fiscal_year');

            // PS-DBM category group heading (e.g., "ALCOHOL OR ACETONE BASED ANTISEPTICS")
            $table->string('category_group', 255)->nullable()->after('part');

            // Original row position in the APP-CSE template — preserves DBM-mandated row order
            $table->unsignedSmallInteger('row_position')->default(0)->after('category_group');

            // Part I prices are fixed by PS-DBM; Part II prices are filled by the office
            $table->boolean('is_price_fixed')->default(true)->after('row_position');

            // Inflation provision percentage applied at export (default 10% per DBM)
            // Stored at catalogue level so it can be overridden per upload
            // (not per-item — applied to the part total at export time via ppmp_settings)
        });

        // Add index for part-based queries
        Schema::table('ppmp_catalogue', function (Blueprint $table) {
            $table->index(['fiscal_year', 'part', 'row_position']);
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_catalogue', function (Blueprint $table) {
            $table->dropIndex(['fiscal_year', 'part', 'row_position']);
            $table->dropColumn(['part', 'category_group', 'row_position', 'is_price_fixed']);
        });
    }
};
