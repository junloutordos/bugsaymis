<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('buildings', function (Blueprint $table) {
            if (!Schema::hasColumn('buildings', 'building_use')) {
                $table->json('building_use')->nullable()->after('name');
            }
            if (!Schema::hasColumn('buildings', 'number_of_floors')) {
                $table->integer('number_of_floors')->nullable()->after('building_use');
            }
            if (!Schema::hasColumn('buildings', 'year_constructed')) {
                $table->integer('year_constructed')->nullable()->after('number_of_floors');
            }
            if (!Schema::hasColumn('buildings', 'year_completed')) {
                $table->integer('year_completed')->nullable()->after('year_constructed');
            }
            if (!Schema::hasColumn('buildings', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable()->after('year_completed');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('buildings', function (Blueprint $table) {
            if (Schema::hasColumn('buildings', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('buildings', 'year_completed')) {
                $table->dropColumn('year_completed');
            }
            if (Schema::hasColumn('buildings', 'year_constructed')) {
                $table->dropColumn('year_constructed');
            }
            if (Schema::hasColumn('buildings', 'number_of_floors')) {
                $table->dropColumn('number_of_floors');
            }
            if (Schema::hasColumn('buildings', 'building_use')) {
                $table->dropColumn('building_use');
            }
        });
    }
};
