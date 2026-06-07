<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── ppmp_items: compliance columns ────────────────────────────────────
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->string('fund_source', 20)->default('mooe')->after('remarks');
            $table->tinyInteger('procurement_quarter')->unsigned()->nullable()->after('fund_source');
            $table->tinyInteger('delivery_quarter')->unsigned()->nullable()->after('procurement_quarter');
            $table->string('price_source', 100)->nullable()->after('delivery_quarter');
            $table->date('price_validity_date')->nullable()->after('price_source');
        });

        // ── ppmp: supplemental + BAC review fields ────────────────────────────
        Schema::table('ppmp', function (Blueprint $table) {
            $table->boolean('is_supplemental')->default(false)->after('consolidated_at');
            $table->unsignedBigInteger('parent_ppmp_id')->nullable()->after('is_supplemental');
            $table->timestamp('bac_reviewed_at')->nullable()->after('parent_ppmp_id');
            $table->unsignedBigInteger('bac_reviewed_by')->nullable()->after('bac_reviewed_at');
            $table->text('bac_remarks')->nullable()->after('bac_reviewed_by');

            $table->foreign('parent_ppmp_id')->references('id')->on('ppmp')->nullOnDelete();
            $table->foreign('bac_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn(['fund_source', 'procurement_quarter', 'delivery_quarter', 'price_source', 'price_validity_date']);
        });

        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropForeign(['parent_ppmp_id']);
            $table->dropForeign(['bac_reviewed_by']);
            $table->dropColumn(['is_supplemental', 'parent_ppmp_id', 'bac_reviewed_at', 'bac_reviewed_by', 'bac_remarks']);
        });
    }
};
